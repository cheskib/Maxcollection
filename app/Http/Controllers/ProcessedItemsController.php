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
        'player_name', 'title', 'manufacturer', 'set_name',
        'year', 'card_number', 'issue_number', 'country',
    ];

    /**
     * Metadata fields that can be filtered by exact value.
     */
    private const FILTER_FIELDS = ['category', 'sport', 'year', 'team', 'manufacturer', 'card_type'];

    public function index(Request $request): Response
    {
        $sort = $request->string('sort', 'newest')->toString();
        $search = trim($request->string('q')->toString());
        $collection = $request->string('collection')->toString();

        $filters = [];
        foreach (self::FILTER_FIELDS as $field) {
            $filters[$field] = $request->string($field)->toString();
        }

        $items = Item::where('status', Item::STATUS_PROCESSED)
            ->when($collection === 'unassigned', fn ($query) => $query->whereNull('collection_id'))
            ->when($collection !== '' && $collection !== 'unassigned', fn ($query) => $query->where('collection_id', (int) $collection))
            ->where(function ($query) use ($filters) {
                foreach (array_filter($filters) as $field => $value) {
                    $query->whereHas('metadata', fn ($metadata) => $metadata->where($field, $value));
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('metadata', function ($metadata) use ($search) {
                    $metadata->where(function ($where) use ($search) {
                        foreach (self::SEARCH_FIELDS as $field) {
                            $where->orWhere($field, 'like', "%{$search}%");
                        }
                    });
                });
            })
            ->with(['metadata', 'images' => fn ($query) => $query->orderBy('id')])
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'thumbnailImageId' => $item->images->first()?->id,
                'thumbnailVersion' => $item->images->first()?->versionTag() ?? '0',
                'title' => $item->metadata?->primaryTitle() ?? "Item #{$item->id}",
                'category' => $item->metadata?->categoryLabel() ?? 'Unknown',
                'confidence' => $item->metadata?->confidence,
                'processedAt' => $item->processed_at?->format('M j, Y g:i A'),
            ]);

        $items = match ($sort) {
            'oldest' => $items->sortBy('id')->values(),
            'title' => $items->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            default => $items->sortByDesc('id')->values(),
        };

        // Each dropdown offers only values that exist among processed items.
        $options = [];
        foreach (self::FILTER_FIELDS as $field) {
            $values = Metadata::whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED))
                ->whereNotNull($field)
                ->distinct()
                ->orderBy($field)
                ->pluck($field);
            $options[$field] = ($field === 'year' ? $values->sortDesc()->values() : $values)->all();
        }

        return Inertia::render('ProcessedItems', [
            'items' => $items->all(),
            'sort' => $sort,
            'search' => $search,
            'collection' => $collection,
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
            'filters' => $filters,
            'filterOptions' => $options,
        ]);
    }
}
