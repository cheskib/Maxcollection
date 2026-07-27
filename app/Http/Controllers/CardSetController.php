<?php

namespace App\Http\Controllers;

use App\Models\CardSet;
use App\Models\Metadata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardSetController extends Controller
{
    public function index(): Response
    {
        $sets = CardSet::orderByDesc('year')->orderBy('manufacturer')->get();

        return Inertia::render('Sets', [
            'sets' => $sets->map(fn (CardSet $set) => [
                'id' => $set->id,
                'name' => $set->displayName(),
                'cardCount' => $set->cardsQuery()->count(),
                'hasDescription' => $set->description !== null,
            ])->all(),
        ]);
    }

    public function show(CardSet $cardSet): Response
    {
        $cards = $cardSet->cardsQuery()
            ->with(['item.images' => fn ($query) => $query->orderBy('id')])
            ->get()
            ->sortBy('player_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Metadata $metadata) => [
                'itemId' => $metadata->item_id,
                'title' => $metadata->primaryTitle(),
                'cardType' => $metadata->card_type,
                'thumbnailImageId' => $metadata->item?->images->first()?->id,
                'thumbnailVersion' => $metadata->item?->images->first()?->versionTag() ?? '0',
            ]);

        return Inertia::render('SetDetail', [
            'set' => [
                'id' => $cardSet->id,
                'name' => $cardSet->displayName(),
                'description' => $cardSet->description,
            ],
            'cards' => $cards->all(),
        ]);
    }

    /**
     * The owner's wording always wins over the AI's write-up.
     */
    public function update(Request $request, CardSet $cardSet): RedirectResponse
    {
        $validated = $request->validate(['description' => ['nullable', 'string', 'max:5000']]);

        $cardSet->update(['description' => $validated['description'] ?: null]);

        return back()->with('status', 'Set description saved.');
    }
}
