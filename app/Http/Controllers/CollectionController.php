<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Item;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(): Response
    {
        $collections = Collection::withCount('items')->orderBy('name')->get();

        return Inertia::render('Collections', [
            'collections' => $collections->map(fn (Collection $collection) => [
                'id' => $collection->id,
                'name' => $collection->name,
                'itemCount' => $collection->items_count,
            ])->all(),
            'unassignedCount' => Item::whereNull('collection_id')->count(),
        ]);
    }

    public function show(Request $request, string $collection): Response
    {
        $model = $collection === 'unassigned' ? null : Collection::findOrFail((int) $collection);

        $items = Item::when(
            $model,
            fn ($query) => $query->where('collection_id', $model->id),
            fn ($query) => $query->whereNull('collection_id'),
        )
            ->with(['metadata', 'images' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'thumbnailImageId' => $item->images->first()?->id,
                'thumbnailRotation' => $item->images->first()?->rotation ?? 0,
                'title' => $item->metadata?->primaryTitle() ?? "Item #{$item->id}",
                'category' => $item->metadata?->categoryLabel() ?? 'Not processed',
                'status' => $item->status,
            ]);

        return Inertia::render('CollectionDetail', [
            'collection' => [
                'id' => $model?->id,
                'name' => $model?->name ?? 'Unassigned',
            ],
            'items' => $items->all(),
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
