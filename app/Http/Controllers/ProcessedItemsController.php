<?php

namespace App\Http\Controllers;

use App\Models\Item;
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

    public function index(Request $request): Response
    {
        $sort = $request->string('sort', 'newest')->toString();
        $search = trim($request->string('q')->toString());

        $items = Item::where('status', Item::STATUS_PROCESSED)
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

        return Inertia::render('ProcessedItems', [
            'items' => $items->all(),
            'sort' => $sort,
            'search' => $search,
        ]);
    }
}
