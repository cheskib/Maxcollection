<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImageRequest;
use App\Models\Image;
use App\Models\Item;
use App\Services\CaptureService;
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
        return Inertia::render('Capture', ['item' => null]);
    }

    public function show(Item $item): Response
    {
        return Inertia::render('Capture', [
            'item' => [
                'id' => $item->id,
                'images' => $item->images()->orderBy('id')->get(['id', 'original_filename', 'rotation'])->all(),
            ],
        ]);
    }

    public function storeImage(StoreImageRequest $request): RedirectResponse
    {
        $item = $request->filled('item_id')
            ? Item::findOrFail($request->integer('item_id'))
            : null;

        $item = $this->capture->storeImage($request->user(), $item, $request->file('photo'));

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
