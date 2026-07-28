<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Metadata;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Duplicate-card awareness: cards owned more than once, grouped by
 * identity (manufacturer + year + player + card number). Used for trade
 * lists — "I have 2 spare Mattinglys".
 */
class DuplicatesController extends Controller
{
    public function index(): Response
    {
        $groups = Metadata::whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED))
            ->where('category', 'sports_card')
            ->whereNotNull('manufacturer')
            ->whereNotNull('year')
            ->selectRaw("manufacturer, year, coalesce(player_name, '') as player, coalesce(card_number, '') as number")
            ->selectRaw('count(*) as copies, min(item_id) as first_item_id')
            ->groupByRaw("manufacturer, year, coalesce(player_name, ''), coalesce(card_number, '')")
            ->havingRaw('count(*) > 1')
            ->orderByDesc('copies')
            ->get();

        $thumbnails = Item::with(['images' => fn ($query) => $query->orderBy('id')])
            ->whereIn('id', $groups->pluck('first_item_id'))
            ->get()
            ->keyBy('id');

        return Inertia::render('Duplicates', [
            'groups' => $groups->map(function ($group) use ($thumbnails) {
                $item = $thumbnails->get($group->first_item_id);
                $image = $item?->images->first();

                return [
                    'title' => trim(collect([$group->year, $group->manufacturer, $group->player])->filter()->implode(' ')
                        .($group->number !== '' ? " #{$group->number}" : '')),
                    'copies' => (int) $group->copies,
                    'itemId' => (int) $group->first_item_id,
                    'thumbnailImageId' => $image?->id,
                    'thumbnailVersion' => $image?->versionTag() ?? '0',
                ];
            })->all(),
        ]);
    }
}
