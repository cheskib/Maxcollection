<?php

namespace App\Services;

use App\Jobs\ProcessItemJob;
use App\Models\Batch;
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

        $item->metadata()->updateOrCreate([], [
            'category' => $result->category,
            'confidence' => $result->confidence,
            ...$fields,
        ]);

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
     * Auto-orient and auto-trim photographs. When the AI saw the cleaned
     * renderings its answers are additional adjustments on top; when it saw
     * the original photos its rotation is absolute. The user's rotate and
     * trim controls remain the final authority afterwards.
     */
    private function applyRotations(Item $item, AiResult $result, string $source = AiService::SOURCE_CLEANED): void
    {
        if ($result->rotations === [] && $result->tilts === [] && $result->trims === [] && $result->roles === []) {
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

            $reported = $result->rotations[$index] ?? 0;

            $rotation = $source === AiService::SOURCE_ORIGINAL
                ? $reported
                : ($image->rotation + $reported) % 360;

            if ($rotation !== $image->rotation && ($source === AiService::SOURCE_ORIGINAL || $reported !== 0)) {
                $changes['rotation'] = $rotation;
            }

            // Fine straightening is only for crooked hand-held photos:
            // single captures. Scanner and PDF batches come in straight.
            if ($item->batch_id === null) {
                $reportedTilt = $result->tilts[$index] ?? 0.0;

                $tilt = $source === AiService::SOURCE_ORIGINAL
                    ? $reportedTilt
                    : max(-45.0, min(45.0, $image->tilt + $reportedTilt));

                if ($tilt !== $image->tilt && ($source === AiService::SOURCE_ORIGINAL || $reportedTilt !== 0.0)) {
                    $changes['tilt'] = $tilt;
                }
            }

            $trim = $result->trims[$index] ?? null;

            if ($source === AiService::SOURCE_ORIGINAL && $trim !== null) {
                // Reprocessing from originals redoes the adjustments from
                // scratch: the AI's trim describes the untouched photo and
                // replaces whatever trim was there before.
                foreach (['top', 'right', 'bottom', 'left'] as $edge) {
                    $value = $trim[$edge] ?? 0;

                    if ($value !== $image->{"crop_{$edge}"}) {
                        $changes["crop_{$edge}"] = $value;
                    }
                }
            } elseif ($trim !== null && ! $image->hasCrop() && $item->batch_id === null) {
                // Automatic trims are for hand-held single captures only;
                // scanner and PDF batches arrive already framed (owner
                // decision — batch items are only trimmed when the user
                // explicitly reprocesses from originals). They also apply
                // just to untrimmed photos: they would stack (the AI sees
                // the already-trimmed rendering) and cut into the item.
                foreach (['top', 'right', 'bottom', 'left'] as $edge) {
                    $value = min(45, $trim[$edge]);

                    if ($value !== $image->{"crop_{$edge}"}) {
                        $changes["crop_{$edge}"] = $value;
                    }
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
