<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Item;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * Value totals per collection: each card contributes its Our Value
     * range, falling back to the AI Ballpark when none was entered.
     *
     * @return array<int|string, object{from: string|float|null, to: string|float|null}>
     */
    private function valueTotals(): array
    {
        return DB::table('items')
            ->join('metadata', 'metadata.item_id', '=', 'items.id')
            // Sold/gifted/lost cards no longer contribute value.
            ->where(fn ($query) => $query->whereNull('items.disposition')->orWhere('items.disposition', '!=', Item::DISPOSITION_GONE))
            ->selectRaw("coalesce(items.collection_id, 0) as cid")
            ->selectRaw('sum(coalesce(metadata.value_from, metadata.ai_value_from)) as value_from')
            ->selectRaw('sum(coalesce(metadata.value_to, metadata.ai_value_to)) as value_to')
            ->groupBy('cid')
            ->get()
            ->keyBy('cid')
            ->all();
    }

    public function index(): Response
    {
        $collections = Collection::withCount(['items' => fn ($query) => $query->owned()])->orderBy('name')->get();
        $totals = $this->valueTotals();

        $range = fn (int $key) => [
            'from' => isset($totals[$key]->value_from) ? round((float) $totals[$key]->value_from, 2) : null,
            'to' => isset($totals[$key]->value_to) ? round((float) $totals[$key]->value_to, 2) : null,
        ];

        return Inertia::render('Collections', [
            'collections' => $collections->map(fn (Collection $collection) => [
                'id' => $collection->id,
                'name' => $collection->name,
                'itemCount' => $collection->items_count,
                'value' => $range($collection->id),
            ])->all(),
            'unassignedCount' => Item::query()->owned()->whereNull('collection_id')->count(),
            'unassignedValue' => $range(0),
        ]);
    }

    public function show(Request $request, string $collection): Response
    {
        $model = $collection === 'unassigned' ? null : Collection::findOrFail((int) $collection);
        $sort = $request->string('sort', 'newest')->toString();

        $query = Item::when(
            $model,
            fn ($query) => $query->where('collection_id', $model->id),
            fn ($query) => $query->whereNull('collection_id'),
        )
            ->with(['metadata', 'images' => fn ($query) => $query->orderBy('id')]);

        // Sorting by value uses each card's Our Value, falling back to the
        // AI Ballpark; unvalued cards sort last.
        if ($sort === 'value') {
            $query
                ->leftJoin('metadata', 'metadata.item_id', '=', 'items.id')
                ->orderByRaw('coalesce(metadata.value_to, metadata.ai_value_to) is null')
                ->orderByRaw('coalesce(metadata.value_to, metadata.ai_value_to) desc')
                ->select('items.*');
        } else {
            $query->orderByDesc('items.id');
        }

        $items = $query->get()
            ->map(function (Item $item) {
                $metadata = $item->metadata;
                $from = $metadata?->value_from ?? $metadata?->ai_value_from;
                $to = $metadata?->value_to ?? $metadata?->ai_value_to;

                return [
                    'id' => $item->id,
                    'thumbnailImageId' => $item->images->first()?->id,
                    'thumbnailVersion' => $item->images->first()?->versionTag() ?? '0',
                    'title' => $metadata?->primaryTitle() ?? "Item #{$item->id}",
                    'category' => $metadata?->categoryLabel() ?? 'Not processed',
                    'status' => $item->status,
                    'value' => ['from' => $from, 'to' => $to, 'isOurs' => $metadata?->value_from !== null || $metadata?->value_to !== null],
                ];
            });

        $totals = $this->valueTotals();
        $totalKey = $model?->id ?? 0;

        return Inertia::render('CollectionDetail', [
            'collection' => [
                'id' => $model?->id,
                'name' => $model?->name ?? 'Unassigned',
                'value' => [
                    'from' => isset($totals[$totalKey]->value_from) ? round((float) $totals[$totalKey]->value_from, 2) : null,
                    'to' => isset($totals[$totalKey]->value_to) ? round((float) $totals[$totalKey]->value_to, 2) : null,
                ],
            ],
            'items' => $items->all(),
            'sort' => $sort,
        ]);
    }

    /**
     * Change one item's collection from its detail page.
     */
    public function assignItem(Request $request, Item $item, CollectionService $collections): RedirectResponse
    {
        $request->validate(CollectionService::rules());

        $item->update(['collection_id' => $collections->resolveFromRequest($request, $request->user())]);

        return back()->with('status', 'Collection updated.');
    }
}
