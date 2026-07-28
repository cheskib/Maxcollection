<?php

namespace App\Services;

use App\Jobs\DescribeSetJob;
use App\Jobs\ProcessItemJob;
use App\Models\Batch;
use App\Models\CardSet;
use App\Models\Item;
use App\Models\ProcessingJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessingService
{
    public function __construct(
        private readonly AiService $ai,
        private readonly ThumbnailService $thumbnails,
    ) {
    }

    /**
     * Queue every unprocessed (captured) item. Each item gets its own queue
     * job so one failure never affects the others (PROJECT.md rule 8).
     */
    public function queueUnprocessedItems(): int
    {
        $items = Item::where('status', Item::STATUS_CAPTURED)->get();

        foreach ($items as $item) {
            $this->queueItem($item);
        }

        return $items->count();
    }

    /**
     * Queue a single item for (re)processing at the given tier, reading
     * either the cleaned renderings or the untouched original photos.
     */
    public function queueItem(Item $item, string $tier = AiService::TIER_STANDARD, string $source = AiService::SOURCE_CLEANED): ProcessingJob
    {
        $job = $item->processingJobs()->create(['status' => ProcessingJob::STATUS_QUEUED]);
        $job->logs()->create(['message' => "Item queued for {$tier} processing ({$source} photos)."]);

        $item->update(['status' => Item::STATUS_QUEUED, 'review_reason' => null]);

        ProcessItemJob::dispatch($job->id, $tier, $source);

        return $job;
    }

    /**
     * Execute one processing job. Runs inside the queue worker.
     */
    public function processJob(ProcessingJob $job, string $tier = AiService::TIER_STANDARD, string $source = AiService::SOURCE_CLEANED): void
    {
        $item = $job->item;

        $job->update(['status' => ProcessingJob::STATUS_PROCESSING, 'started_at' => now()]);
        $item->update(['status' => Item::STATUS_PROCESSING]);

        try {
            $result = $this->ai->identify($item, $job, $tier, $source);

            $this->applyResult($item, $job, $result, $source);

            $job->update(['status' => ProcessingJob::STATUS_COMPLETED, 'finished_at' => now()]);
            $job->logs()->create(['message' => 'Processing completed.']);
        } catch (\Throwable $e) {
            Log::error('Item processing failed', ['item_id' => $item->id, 'exception' => $e]);

            $job->update([
                'status' => ProcessingJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $job->logs()->create(['level' => 'error', 'message' => 'Processing failed: '.$e->getMessage()]);

            // Failed items require manual attention (PROJECT.md section 17).
            $item->update([
                'status' => Item::STATUS_NEEDS_REVIEW,
                'review_reason' => 'ai_failure',
                'processed_at' => now(),
            ]);
        }
    }

    /**
     * Save the AI result and route the item to Processed or Needs Review.
     */
    private function applyResult(Item $item, ProcessingJob $job, AiResult $result, string $source = AiService::SOURCE_CLEANED): void
    {
        $this->applyRotations($item, $result, $source);

        // The capture wizard's autograph answer is user-provided; the user
        // always overrides the AI (PROJECT.md rule 4).
        $fields = $result->fields;
        $existingAutograph = $item->metadata?->autograph;

        if ($existingAutograph !== null && array_key_exists('autograph', $fields)) {
            $fields['autograph'] = $existingAutograph;
        }

        // The AI sometimes returns "football"; display and grouping (Browse,
        // filters) expect "Football".
        if (filled($fields['sport'] ?? null)) {
            $fields['sport'] = Str::title($fields['sport']);
        }

        if (array_key_exists('card_type', $fields)) {
            $fields['card_type'] = \App\Models\Metadata::normalizeCardType($fields['card_type']);
        }

        $item->metadata()->updateOrCreate([], [
            'category' => $result->category,
            'confidence' => $result->confidence,
            // The AI's ballpark refreshes each run; the owner's manual
            // value range (value_from/value_to) is never written here.
            'ai_value_from' => $result->valueLow,
            'ai_value_to' => $result->valueHigh,
            ...$fields,
        ]);

        $this->registerCardSet($item);

        $threshold = (float) Setting::value('confidence_threshold', '75');
        $reason = $result->reviewReason($threshold);

        $item->update([
            'status' => $reason === null ? Item::STATUS_PROCESSED : Item::STATUS_NEEDS_REVIEW,
            'review_reason' => $reason,
            'processed_at' => now(),
        ]);

        $job->logs()->create([
            'message' => sprintf(
                'Identified as %s with %.0f%% confidence%s.',
                $result->category,
                $result->confidence,
                $reason ? " (needs review: {$reason})" : ''
            ),
        ]);
    }

    /**
     * Auto-orient photographs and fill in photo roles. Framing is the
     * photographer's responsibility (owner decision): the AI never trims
     * or tilts; only quarter-turn rotation is corrected. When the AI saw
     * the cleaned renderings its rotation adds on top; when it saw the
     * original photos it is absolute, and any manual trim or tilt is
     * cleared so the photo returns to its original framing.
     */
    private function applyRotations(Item $item, AiResult $result, string $source = AiService::SOURCE_CLEANED): void
    {
        if ($result->rotations === [] && $result->roles === [] && $source !== AiService::SOURCE_ORIGINAL) {
            return;
        }

        foreach ($item->images()->orderBy('id')->get()->values() as $index => $image) {
            $changes = [];

            // The AI classifies front/back/detail for photos that were not
            // explicitly labeled in the capture wizard (user labels win).
            $role = $result->roles[$index] ?? 'unknown';

            if ($image->role === null && $role !== 'unknown') {
                $changes['role'] = $role;
            }

            // A hand-rotated photo is locked: the user's correction always
            // overrides the AI (CLAUDE.md 11), on every future run.
            if (! $image->rotation_locked) {
                $reported = $result->rotations[$index] ?? 0;

                $rotation = $source === AiService::SOURCE_ORIGINAL
                    ? $reported
                    : ($image->rotation + $reported) % 360;

                if ($rotation !== $image->rotation && ($source === AiService::SOURCE_ORIGINAL || $reported !== 0)) {
                    $changes['rotation'] = $rotation;
                }
            }

            // Reprocessing from originals restores the photo's original
            // framing: any manual trim or leftover tilt is cleared.
            if ($source === AiService::SOURCE_ORIGINAL) {
                foreach (['top', 'right', 'bottom', 'left'] as $edge) {
                    if ($image->{"crop_{$edge}"} !== 0) {
                        $changes["crop_{$edge}"] = 0;
                    }
                }

                if ($image->tilt !== 0.0) {
                    $changes['tilt'] = 0.0;
                }
            }

            if ($changes === []) {
                continue;
            }

            $visualChange = isset($changes['rotation']) || isset($changes['tilt']) || isset($changes['crop_top']) || isset($changes['crop_right']) || isset($changes['crop_bottom']) || isset($changes['crop_left']);

            // Keep the cleanup that is about to be replaced so a bad AI
            // pass can be undone from the item page.
            if ($visualChange) {
                $changes['previous_adjustments'] = $image->adjustmentValues();
            }

            $image->update($changes);

            if ($visualChange) {
                $this->thumbnails->forget($image);
            }
        }
    }

    /**
     * Self-healing: items stuck in queued/processing for over ten minutes
     * (job timeout is five) lost their job to a timeout or restart. Move
     * them to Needs Review so they are visible and reprocessable.
     */
    public function rescueStalledItems(): int
    {
        $stalled = Item::whereIn('status', [Item::STATUS_QUEUED, Item::STATUS_PROCESSING])
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();

        foreach ($stalled as $item) {
            $item->update([
                'status' => Item::STATUS_NEEDS_REVIEW,
                'review_reason' => 'ai_failure',
                'processed_at' => now(),
            ]);

            $item->processingJobs()->latest('id')->first()?->update([
                'status' => ProcessingJob::STATUS_FAILED,
                'error_message' => 'Processing stalled and was rescued.',
                'finished_at' => now(),
            ]);
        }

        return $stalled->count();
    }

    /**
     * The Sets catalog builds itself: the first card seen from a
     * sport/manufacturer/year creates the set profile, and a one-time
     * background job writes its design history.
     */
    private function registerCardSet(Item $item): void
    {
        $metadata = $item->metadata()->first();

        if ($metadata === null || $metadata->category !== 'sports_card'
            || blank($metadata->manufacturer) || blank($metadata->year)) {
            return;
        }

        $set = CardSet::firstOrCreate([
            'sport' => $metadata->sport ?? '',
            'manufacturer' => $metadata->manufacturer,
            'year' => $metadata->year,
            'set_name' => $metadata->set_name ?? '',
        ]);

        if ($set->description === null) {
            DescribeSetJob::dispatch($set->id);
        }
    }

    /**
     * Re-run the AI over every item in a batch (whatever its status),
     * skipping items already waiting in the queue.
     */
    public function reprocessBatch(Batch $batch, string $source = AiService::SOURCE_CLEANED): int
    {
        $items = $batch->items()
            ->whereNotIn('status', [Item::STATUS_QUEUED, Item::STATUS_PROCESSING])
            ->get();

        foreach ($items as $item) {
            $this->queueItem($item, AiService::TIER_STANDARD, $source);
        }

        return $items->count();
    }

    /**
     * Re-run the AI over every item in the collection (whatever its
     * status), skipping items already waiting in the queue.
     */
    public function reprocessAll(string $source = AiService::SOURCE_CLEANED): int
    {
        $items = Item::whereNotIn('status', [Item::STATUS_QUEUED, Item::STATUS_PROCESSING])->get();

        foreach ($items as $item) {
            $this->queueItem($item, AiService::TIER_STANDARD, $source);
        }

        return $items->count();
    }

    /**
     * Queue an AI Ballpark refresh for every item that has metadata.
     * Values only — nothing else about the items is touched.
     */
    public function revalueAll(): int
    {
        $ids = Item::whereHas('metadata')->pluck('id');

        foreach ($ids as $id) {
            \App\Jobs\RevalueItemJob::dispatch($id);
        }

        return $ids->count();
    }

    /**
     * Queue only the captured items belonging to the given batches.
     */
    public function queueBatches(array $batchIds): int
    {
        $items = Item::whereIn('batch_id', $batchIds)
            ->where('status', Item::STATUS_CAPTURED)
            ->get();

        foreach ($items as $item) {
            $this->queueItem($item);
        }

        return $items->count();
    }
}
