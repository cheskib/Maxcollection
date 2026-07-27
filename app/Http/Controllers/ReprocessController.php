<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\AiService;
use App\Services\ProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReprocessController extends Controller
{
    public function store(Request $request, Item $item, ProcessingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'tier' => ['nullable', Rule::in([AiService::TIER_STANDARD, AiService::TIER_PREMIUM])],
            'source' => ['nullable', Rule::in([AiService::SOURCE_CLEANED, AiService::SOURCE_ORIGINAL])],
        ]);

        $tier = $validated['tier'] ?? AiService::TIER_STANDARD;
        $source = $validated['source'] ?? AiService::SOURCE_CLEANED;

        $service->queueItem($item, $tier, $source);

        return redirect()
            ->route('items.show', $item)
            ->with('status', $tier === AiService::TIER_PREMIUM
                ? 'Item queued for premium analysis.'
                : 'Item queued for reprocessing.');
    }

    /**
     * Re-run the AI over the entire collection at the standard tier.
     */
    public function all(Request $request, ProcessingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', Rule::in([AiService::SOURCE_CLEANED, AiService::SOURCE_ORIGINAL])],
        ]);

        $count = $service->reprocessAll($validated['source'] ?? AiService::SOURCE_CLEANED);

        return back()->with('status', "{$count} item(s) queued for reprocessing.");
    }
}
