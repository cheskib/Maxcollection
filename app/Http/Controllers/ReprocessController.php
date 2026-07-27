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
        ]);

        $tier = $validated['tier'] ?? AiService::TIER_STANDARD;

        $service->queueItem($item, $tier);

        return redirect()
            ->route('items.show', $item)
            ->with('status', $tier === AiService::TIER_PREMIUM
                ? 'Item queued for premium analysis.'
                : 'Item queued for reprocessing.');
    }
}
