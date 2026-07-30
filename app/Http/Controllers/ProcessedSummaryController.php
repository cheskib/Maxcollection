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
        // Comics drill Publisher → Age (owner ruling); a chosen publisher
        // is the comics counterpart of a chosen sport.
        $publisher = trim($request->string('publisher')->toString());
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
                ->owned()
                ->when($collectionModel, fn ($items) => $items->where('collection_id', $collectionModel->id))
                ->when($collection === 'unassigned', fn ($items) => $items->whereNull('collection_id')),
        )
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($sport !== '', fn ($query) => $query->where('sport', $sport))
            ->when($publisher !== '', fn ($query) => $query->where('publisher', $publisher));

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
            'publisher' => $publisher !== '' ? $publisher : null,
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
            'ages' => [],
            'collections' => ['named' => [], 'unassigned' => 0],
            'sets' => [],
            'duplicates' => 0,
            'keyCards' => 0,
        ];

        // Level 3: a sport is chosen — break it down by card type.
        if ($sport !== '') {
            return Inertia::render('ProcessedSummary', [
                ...$shared,
                'cardTypes' => $countBy('card_type'),
            ]);
        }

        // Level 3 for comics: a publisher is chosen — break it down by
        // age. Age derives from the year, so bucket the year counts.
        if ($publisher !== '') {
            $byAge = collect($countBy('year'))
                ->groupBy(fn (array $row) => Metadata::comicAge($row['value']) ?? 'Unknown Age')
                ->map(fn ($rows) => $rows->sum('count'));

            $ages = collect(array_keys(Metadata::COMIC_AGE_YEARS))
                ->map(fn (string $age) => ['value' => $age, 'count' => (int) ($byAge[$age] ?? 0)])
                ->filter(fn (array $row) => $row['count'] > 0)
                ->values()
                ->all();

            return Inertia::render('ProcessedSummary', [
                ...$shared,
                'ages' => $ages,
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
            'items as processed_count' => fn ($query) => $query->where('status', Item::STATUS_PROCESSED)->owned(),
        ])->orderBy('name')->get();

        $unassigned = Item::where('status', Item::STATUS_PROCESSED)->owned()->whereNull('collection_id')->count();

        $duplicates = Metadata::whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED)->owned())
            ->where('category', 'sports_card')
            ->whereNotNull('manufacturer')
            ->whereNotNull('year')
            ->selectRaw('count(*) as copies')
            ->groupByRaw("manufacturer, year, coalesce(player_name, ''), coalesce(card_number, '')")
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        $keyCards = Metadata::whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED)->owned())
            ->where('key_card', true)
            ->count();

        return Inertia::render('ProcessedSummary', [
            ...$shared,
            'categories' => $categories,
            'duplicates' => $duplicates,
            'keyCards' => $keyCards,
            'collections' => [
                'named' => $collections->map(fn (Collection $collection) => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'count' => $collection->processed_count,
                ])->all(),
                'unassigned' => $unassigned,
            ],
            // The Sets catalog is hidden for now (owner decision); the
            // section stays empty so the page simply omits it.
            'sets' => [],
        ]);
    }
}
