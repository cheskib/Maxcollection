<?php

namespace App\Services;

use App\Jobs\ProcessItemJob;
use App\Models\Item;
use App\Models\ProcessingJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

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
     * Queue a single item for (re)processing at the given tier.
     */
    public function queueItem(Item $item, string $tier = AiService::TIER_STANDARD): ProcessingJob
    {
        $job = $item->processingJobs()->create(['status' => ProcessingJob::STATUS_QUEUED]);
        $job->logs()->create(['message' => "Item queued for {$tier} processing."]);

        $item->update(['status' => Item::STATUS_QUEUED, 'review_reason' => null]);

        ProcessItemJob::dispatch($job->id, $tier);

        return $job;
    }

    /**
     * Execute one processing job. Runs inside the queue worker.
     */
    public function processJob(ProcessingJob $job, string $tier = AiService::TIER_STANDARD): void
    {
        $item = $job->item;

        $job->update(['status' => ProcessingJob::STATUS_PROCESSING, 'started_at' => now()]);
        $item->update(['status' => Item::STATUS_PROCESSING]);

        try {
            $result = $this->ai->identify($item, $job, $tier);

            $this->applyResult($item, $job, $result);

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
    private function applyResult(Item $item, ProcessingJob $job, AiResult $result): void
    {
        $this->applyRotations($item, $result);

        $item->metadata()->updateOrCreate([], [
            'category' => $result->category,
            'confidence' => $result->confidence,
            ...$result->fields,
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
     * Auto-orient photographs. The AI sees images with their current display
     * rotation already applied, so its answer is an additional turn on top;
     * the user's rotate button remains the final authority afterwards.
     */
    private function applyRotations(Item $item, AiResult $result): void
    {
        if ($result->rotations === []) {
            return;
        }

        foreach ($item->images()->orderBy('id')->get()->values() as $index => $image) {
            $extra = $result->rotations[$index] ?? 0;

            if ($extra === 0) {
                continue;
            }

            $image->update(['rotation' => ($image->rotation + $extra) % 360]);
            $this->thumbnails->forget($image);
        }
    }
}
