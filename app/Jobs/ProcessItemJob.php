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
    ) {
    }

    public function handle(ProcessingService $service): void
    {
        $job = ProcessingJob::find($this->processingJobId);

        if ($job === null || $job->item === null) {
            return; // Item was deleted while waiting in the queue.
        }

        $service->processJob($job, $this->tier);
    }
}
