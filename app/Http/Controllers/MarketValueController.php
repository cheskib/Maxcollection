<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\MarketValueService;
use Illuminate\Http\RedirectResponse;

class MarketValueController extends Controller
{
    /**
     * Refresh one card's live market price on demand.
     */
    public function store(Item $item, MarketValueService $market): RedirectResponse
    {
        $metadata = $item->metadata;

        if ($metadata === null) {
            return back()->with('status', 'This item has no details yet — process it first.');
        }

        try {
            $match = $market->refresh($metadata);
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', $match === null
            ? 'No market match found for this card.'
            : "Market price updated (matched: {$match}).");
    }
}
