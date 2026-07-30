<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Item;
use App\Models\Metadata;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcessedItemsController extends Controller
{
    /**
     * The metadata columns covered by keyword search (PROJECT.md section 18).
     */
    private const SEARCH_FIELDS = [
        'player_name', 'title', 'manufacturer',
        'year', 'card_number', 'issue_number', 'country',
    ];

    /**
     * Metadata fields that can be filtered by exact value.
     */
    private const FILTER_FIELDS = ['category', 'sport', 'year', 'team', 'manufacturer', 'card_type', 'publisher', 'country', 'format', 'genre'];

    public function index(Request $request): Response
    {
        $sort = $request->string('sort', 'newest')->toString();
        $search = trim($request->string('q')->toString());
        $collection = $request->string('collection')->toString();

        $filters = [];
        foreach (self::FILTER_FIELDS as $field) {
            $filters[$field] = $request->string($field)->toString();
        }

        // Comic age is derived, not stored: the filter translates to the
        // age's year range (Metadata::COMIC_AGE_YEARS).
        $age = $request->string('age')->toString();
        $ageYears = Metadata::COMIC_AGE_YEARS[$age] ?? null;

        $keyOnly = $request->boolean('key');
        // Review triage: only cards the AI was less sure about.
        $confidenceBelow = (int) $request->integer('confidence_below');
        $confidenceBelow = $confidenceBelow > 0 && $confidenceBelow <= 100 ? $confidenceBelow : null;

        $items = Item::where('status', Item::STATUS_PROCESSED)
            ->owned()
            ->when($keyOnly, fn ($query) => $query->whereHas('metadata', fn ($metadata) => $metadata->where('key_card', true)))
            ->when($confidenceBelow !== null, fn ($query) => $query->whereHas('metadata', fn ($metadata) => $metadata->where('confidence', '<', $confidenceBelow)))
            ->when($collection === 'unassigned', fn ($query) => $query->whereNull('collection_id'))
            ->when($collection !== '' && $collection !== 'unassigned', fn ($query) => $query->where('collection_id', (int) $collection))
            ->where(function ($query) use ($filters) {
                foreach (array_filter($filters) as $field => $value) {
                    $query->whereHas('metadata', fn ($metadata) => $metadata->where($field, $value));
                }
            })
            ->when($ageYears !== null, fn ($query) => $query->whereHas(
                'metadata',
                fn ($metadata) => $metadata->whereBetween('year', $ageYears),
            ))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('metadata', function ($metadata) use ($search) {
                    $metadata->where(function ($where) use ($search) {
                        foreach (self::SEARCH_FIELDS as $field) {
                            $where->orWhere($field, 'like', "%{$search}%");
                        }
                    });
                });
            })
            ->with(['metadata', 'images' => fn ($query) => $query->orderBy('id')]);

        // Sort and page in SQL: only one page of cards is ever loaded, so
        // the list keeps working at any collection size.
        $items = match ($sort) {
            'oldest' => $items->orderBy('items.id'),
            'title' => $items
                ->leftJoin('metadata', 'metadata.item_id', '=', 'items.id')
                ->orderByRaw("coalesce(metadata.player_name, metadata.title, metadata.country, '')")
                ->select('items.*'),
            'value' => $items
                ->leftJoin('metadata', 'metadata.item_id', '=', 'items.id')
                ->orderByRaw('coalesce(metadata.value_to, metadata.ai_value_to) is null')
                ->orderByRaw('coalesce(metadata.value_to, metadata.ai_value_to) desc')
                ->select('items.*'),
            'confidence' => $items
                ->leftJoin('metadata', 'metadata.item_id', '=', 'items.id')
                ->orderByRaw('metadata.confidence is null')
                ->orderBy('metadata.confidence')
                ->select('items.*'),
            default => $items->orderByDesc('items.id'),
        };

        $paginated = $items->paginate(60)->withQueryString();

        $pageItems = collect($paginated->items())
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'thumbnailImageId' => $item->images->first()?->id,
                'thumbnailVersion' => $item->images->first()?->versionTag() ?? '0',
                'title' => $item->metadata?->primaryTitle() ?? "Item #{$item->id}",
                'category' => $item->metadata?->categoryLabel() ?? 'Unknown',
                'confidence' => $item->metadata?->confidence,
                'processedAt' => $item->processed_at?->format('M j, Y g:i A'),
                'value' => [
                    'from' => $item->metadata?->value_from ?? $item->metadata?->ai_value_from,
                    'to' => $item->metadata?->value_to ?? $item->metadata?->ai_value_to,
                    'isOurs' => $item->metadata?->value_from !== null || $item->metadata?->value_to !== null,
                ],
                'keyCard' => (bool) $item->metadata?->key_card,
            ]);

        // Each dropdown offers only values that exist among processed items.
        $options = [];
        foreach (self::FILTER_FIELDS as $field) {
            $values = Metadata::whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED)->owned())
                ->whereNotNull($field)
                ->distinct()
                ->orderBy($field)
                ->pluck($field);
            $options[$field] = ($field === 'year' ? $values->sortDesc()->values() : $values)->all();
        }

        return Inertia::render('ProcessedItems', [
            'items' => $pageItems->all(),
            'keyOnly' => $keyOnly,
            'confidenceBelow' => $confidenceBelow,
            'page' => [
                'current' => $paginated->currentPage(),
                'last' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
            'sort' => $sort,
            'search' => $search,
            'collection' => $collection,
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
            'filters' => [...$filters, 'age' => $ageYears !== null ? $age : ''],
            'filterOptions' => [...$options, 'age' => array_keys(Metadata::COMIC_AGE_YEARS)],
        ]);
    }
}
