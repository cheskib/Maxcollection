<?php

namespace App\Services;

use App\Jobs\ProcessItemJob;
use App\Models\Item;
use App\Models\ProcessingJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class ProcessingService
{
    public function __construct(private readonly AiService $ai)
    {
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
     * Queue a single item for (re)processing.
     */
    public function queueItem(Item $item): ProcessingJob
    {
        $job = $item->processingJobs()->create(['status' => ProcessingJob::STATUS_QUEUED]);
        $job->logs()->create(['message' => 'Item queued for processing.']);

        $item->update(['status' => Item::STATUS_QUEUED, 'review_reason' => null]);

        ProcessItemJob::dispatch($job->id);

        return $job;
    }

    /**
     * Execute one processing job. Runs inside the queue worker.
     */
    public function processJob(ProcessingJob $job): void
    {
        $item = $job->item;

        $job->update(['status' => ProcessingJob::STATUS_PROCESSING, 'started_at' => now()]);
        $item->update(['status' => Item::STATUS_PROCESSING]);

        try {
            $result = $this->ai->identify($item, $job);

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
}
