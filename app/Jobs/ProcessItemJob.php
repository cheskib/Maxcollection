<?php

namespace App\Jobs;

use App\Models\ProcessingJob;
use App\Services\AiService;
use App\Services\ProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessItemJob implements ShouldQueue
{
    use Queueable;

    // Failures are recorded by ProcessingService and reviewed manually;
    // automatic retries would hide them.
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $processingJobId,
        public readonly string $tier = AiService::TIER_STANDARD,
        public readonly string $source = AiService::SOURCE_CLEANED,
    ) {
    }

    public function handle(ProcessingService $service): void
    {
        // Admin hold: the item stays queued and its job row stays open;
        // releasing the hold re-dispatches everything still queued.
        if (\App\Models\Setting::value('ai_hold') === '1') {
            return;
        }

        $job = ProcessingJob::find($this->processingJobId);

        if ($job === null || $job->item === null) {
            return; // Item was deleted while waiting in the queue.
        }

        $service->processJob($job, $this->tier, $this->source);
    }

    /**
     * A timeout or worker crash kills the process before processJob's own
     * error handling can run; without this the item would stay stuck in
     * "processing" forever.
     */
    public function failed(?\Throwable $exception): void
    {
        $job = ProcessingJob::find($this->processingJobId);
        $item = $job?->item;

        if ($job === null || $item === null) {
            return;
        }

        $job->update([
            'status' => ProcessingJob::STATUS_FAILED,
            'error_message' => $exception?->getMessage() ?? 'Processing was interrupted.',
            'finished_at' => now(),
        ]);
        $job->logs()->create(['level' => 'error', 'message' => 'Processing was interrupted (timeout or restart).']);

        $item->update([
            'status' => \App\Models\Item::STATUS_NEEDS_REVIEW,
            'review_reason' => 'ai_failure',
            'processed_at' => now(),
        ]);
    }
}
