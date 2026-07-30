<?php

namespace App\Jobs;

use App\Models\Item;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refresh one item's AI Ballpark value from its photos. Touches nothing
 * else: metadata, status, and the owner's manual value stay as they are.
 */
class RevalueItemJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $itemId)
    {
    }

    public function handle(AiService $ai): void
    {
        // Admin hold pauses all AI work.
        if (\App\Models\Setting::value('ai_hold') === '1') {
            return;
        }

        $item = Item::find($this->itemId);
        $metadata = $item?->metadata;

        if ($item === null || $metadata === null) {
            return;
        }

        $range = $ai->estimateValue($item);

        // A failed or declined estimate keeps whatever was there before.
        if ($range !== null) {
            $metadata->update(['ai_value_from' => $range['low'], 'ai_value_to' => $range['high']]);
        }
    }
}
