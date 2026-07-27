<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkItemRequest;
use App\Jobs\ImportPdfJob;
use App\Models\Item;
use App\Services\CaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Accept a scanned PDF (pages in front/back order) and convert it into
     * items in the background.
     */
    public function storePdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
            'photos_per_item' => ['required', 'integer', 'in:1,2'],
        ]);

        $path = $request->file('pdf')->store('imports', 'local');

        ImportPdfJob::dispatch($path, (int) $validated['photos_per_item'], $request->user()->id);

        return response()->json([
            'message' => 'PDF received. Items will appear on the Home screen shortly.',
        ], 202);
    }
}
