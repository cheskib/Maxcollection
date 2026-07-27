<?php

namespace App\Jobs;

use App\Models\CardSet;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DescribeSetJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $cardSetId)
    {
    }

    public function handle(AiService $ai): void
    {
        $set = CardSet::find($this->cardSetId);

        // Only fill empty profiles: a description the user has (or the AI
        // already) written is never overwritten.
        if ($set === null || $set->description !== null) {
            return;
        }

        $description = $ai->describeSet($set);

        if ($description !== null) {
            $set->update(['description' => $description]);
        }
    }
}
