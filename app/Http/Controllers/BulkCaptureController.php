<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkItemRequest;
use App\Models\Item;
use App\Services\CaptureService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BulkCaptureController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('BulkCapture');
    }

    /**
     * Create one item from a group of photographs. The bulk screen calls
     * this once per collectible, so grouping stays under the user's control.
     */
    public function store(StoreBulkItemRequest $request, CaptureService $capture): JsonResponse
    {
        $item = null;

        foreach ($request->file('photos') as $photo) {
            $item = $capture->storeImage($request->user(), $item, $photo);
        }

        /** @var Item $item */
        return response()->json([
            'itemId' => $item->id,
            'photos' => $item->images()->count(),
        ], 201);
    }
}
