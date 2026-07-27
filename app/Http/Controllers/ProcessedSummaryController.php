<?php

namespace App\Http\Controllers;

use App\Models\CardSet;
use App\Models\Collection;
use App\Models\Item;
use App\Models\Metadata;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Items Processed click-through: breakdowns of the processed
 * collection, every row opening the Processed Items list pre-filtered.
 */
class ProcessedSummaryController extends Controller
{
    public function index(): Response
    {
        $processedMetadata = fn () => Metadata::whereHas(
            'item',
            fn ($query) => $query->where('status', Item::STATUS_PROCESSED),
        );

        $countBy = function (string $field) use ($processedMetadata): array {
            return $processedMetadata()
                ->whereNotNull($field)
                ->selectRaw("{$field} as value, count(*) as total")
                ->groupBy($field)
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['value' => $row->value, 'count' => (int) $row->total])
                ->all();
        };

        $categories = collect($countBy('category'))
            ->map(fn (array $row) => [
                ...$row,
                'label' => (new Metadata(['category' => $row['value']]))->categoryLabel(),
            ])->all();

        $collections = Collection::withCount([
            'items as processed_count' => fn ($query) => $query->where('status', Item::STATUS_PROCESSED),
        ])->orderBy('name')->get();

        $unassigned = Item::where('status', Item::STATUS_PROCESSED)->whereNull('collection_id')->count();

        return Inertia::render('ProcessedSummary', [
            'total' => Item::where('status', Item::STATUS_PROCESSED)->count(),
            'categories' => $categories,
            'sports' => $countBy('sport'),
            'cardTypes' => $countBy('card_type'),
            'collections' => [
                'named' => $collections->map(fn (Collection $collection) => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'count' => $collection->processed_count,
                ])->all(),
                'unassigned' => $unassigned,
            ],
            'sets' => CardSet::orderByDesc('year')->orderBy('manufacturer')->get()
                ->map(fn (CardSet $set) => [
                    'id' => $set->id,
                    'name' => $set->displayName(),
                    'count' => $set->cardsQuery()->count(),
                ])
                ->filter(fn (array $set) => $set['count'] > 0)
                ->values()
                ->all(),
        ]);
    }
}
