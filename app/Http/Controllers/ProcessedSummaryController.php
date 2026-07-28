<?php

namespace App\Http\Controllers;

use App\Models\CardSet;
use App\Models\Collection;
use App\Models\Item;
use App\Models\Metadata;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Items Processed click-through: a drill-down of summaries, never a
 * flat list. Category first, then (for sports cards) sport, then card
 * type; the final tap opens the Processed Items list pre-filtered.
 */
class ProcessedSummaryController extends Controller
{
    /**
     * The natural second drill-down level for each category: sports cards
     * group by sport (then card type), comics by publisher, coins and
     * stamps by country.
     */
    private const CATEGORY_GROUPS = [
        'sports_card' => 'sport',
        'comic_book' => 'publisher',
        'coin' => 'country',
        'stamp' => 'country',
    ];

    public function index(Request $request): Response
    {
        $category = trim($request->string('category')->toString());
        $sport = trim($request->string('sport')->toString());
        // Optional collection scope: the same drill-down, inside one
        // collection ('unassigned' for cards without one).
        $collection = trim($request->string('collection')->toString());
        $collectionModel = $collection !== '' && $collection !== 'unassigned'
            ? Collection::findOrFail((int) $collection)
            : null;

        $processedMetadata = fn () => Metadata::whereHas(
            'item',
            fn ($query) => $query
                ->where('status', Item::STATUS_PROCESSED)
                ->when($collectionModel, fn ($items) => $items->where('collection_id', $collectionModel->id))
                ->when($collection === 'unassigned', fn ($items) => $items->whereNull('collection_id')),
        )
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($sport !== '', fn ($query) => $query->where('sport', $sport));

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

        $valueTotal = $processedMetadata()
            ->selectRaw('sum(coalesce(value_from, ai_value_from)) as value_from')
            ->selectRaw('sum(coalesce(value_to, ai_value_to)) as value_to')
            ->first();

        $shared = [
            'category' => $category !== '' ? $category : null,
            'categoryLabel' => $category !== '' ? (new Metadata(['category' => $category]))->categoryLabel() : null,
            'sport' => $sport !== '' ? $sport : null,
            'collection' => $collection !== '' ? $collection : null,
            'collectionName' => $collection === 'unassigned' ? 'Unassigned' : $collectionModel?->name,
            'value' => [
                'from' => $valueTotal?->value_from !== null ? round((float) $valueTotal->value_from, 2) : null,
                'to' => $valueTotal?->value_to !== null ? round((float) $valueTotal->value_to, 2) : null,
            ],
            'total' => $processedMetadata()->count(),
            'categories' => [],
            'groupField' => null,
            'groups' => [],
            'cardTypes' => [],
            'collections' => ['named' => [], 'unassigned' => 0],
            'sets' => [],
        ];

        // Level 3: a sport is chosen — break it down by card type.
        if ($sport !== '') {
            return Inertia::render('ProcessedSummary', [
                ...$shared,
                'cardTypes' => $countBy('card_type'),
            ]);
        }

        // Level 2: a category is chosen — break it down by its natural
        // grouping (sport, publisher, or country).
        if ($category !== '') {
            $groupField = self::CATEGORY_GROUPS[$category] ?? null;

            return Inertia::render('ProcessedSummary', [
                ...$shared,
                'groupField' => $groupField,
                'groups' => $groupField !== null ? $countBy($groupField) : [],
            ]);
        }

        // Level 1: the overview — pick a category (plus the cross-category
        // views by collection and by set).
        $categories = collect($countBy('category'))
            ->map(fn (array $row) => [
                ...$row,
                'label' => (new Metadata(['category' => $row['value']]))->categoryLabel(),
            ])->all();

        // Inside a collection the cross-collection sections (collections,
        // sets) stay hidden; only the category drill-down applies.
        if ($collection !== '') {
            return Inertia::render('ProcessedSummary', [
                ...$shared,
                'categories' => $categories,
            ]);
        }

        $collections = Collection::withCount([
            'items as processed_count' => fn ($query) => $query->where('status', Item::STATUS_PROCESSED),
        ])->orderBy('name')->get();

        $unassigned = Item::where('status', Item::STATUS_PROCESSED)->whereNull('collection_id')->count();

        return Inertia::render('ProcessedSummary', [
            ...$shared,
            'categories' => $categories,
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
