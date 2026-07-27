<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkItemRequest;
use App\Jobs\ImportPdfJob;
use App\Models\Batch;
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
     * The first item of a session creates the batch; later calls reuse it.
     */
    public function store(StoreBulkItemRequest $request, CaptureService $capture): JsonResponse
    {
        $batchId = $request->integer('batch_id') ?: null;

        if ($batchId === null) {
            $batchId = Batch::create(['user_id' => $request->user()->id, 'source' => 'bulk'])->id;
        }

        $item = null;

        foreach ($request->file('photos') as $photo) {
            $item = $capture->storeImage($request->user(), $item, $photo, $batchId);
        }

        /** @var Item $item */
        return response()->json([
            'itemId' => $item->id,
            'batchId' => $batchId,
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
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:204800'],
            'photos_per_item' => ['required', 'integer', 'in:1,2'],
        ]);

        $path = $request->file('pdf')->store('imports', 'local');

        $batch = Batch::create([
            'user_id' => $request->user()->id,
            'source' => 'pdf',
            'label' => $request->file('pdf')->getClientOriginalName(),
        ]);

        ImportPdfJob::dispatch($path, (int) $validated['photos_per_item'], $request->user()->id, $batch->id);

        return response()->json([
            'message' => 'PDF received. Items will appear on the Home screen shortly.',
        ], 202);
    }
}
