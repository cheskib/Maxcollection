<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImageRequest;
use App\Models\Collection;
use App\Models\Image;
use App\Models\Item;
use App\Services\CaptureService;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CaptureController extends Controller
{
    public function __construct(private readonly CaptureService $capture)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Capture', [
            'item' => null,
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function show(Item $item): Response
    {
        return Inertia::render('Capture', [
            'item' => [
                'id' => $item->id,
                'images' => $item->images()->orderBy('id')->get(['id', 'original_filename', 'rotation'])->all(),
            ],
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function storeImage(StoreImageRequest $request, CollectionService $collections): RedirectResponse
    {
        $item = $request->filled('item_id')
            ? Item::findOrFail($request->integer('item_id'))
            : null;

        // The collection is chosen before the first photo; later photos
        // attach to the already-created item.
        $collectionId = $item === null ? $collections->resolveFromRequest($request, $request->user()) : null;

        $item = $this->capture->storeImage($request->user(), $item, $request->file('photo'), null, $collectionId);

        return redirect()->route('capture.show', $item);
    }

    public function destroyImage(Image $image): RedirectResponse
    {
        $item = $image->item;

        $itemDeleted = $this->capture->deleteImage($image);

        return $itemDeleted
            ? redirect()->route('capture.create')
            : redirect()->route('capture.show', $item);
    }
}
