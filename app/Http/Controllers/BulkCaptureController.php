<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkItemRequest;
use App\Jobs\ImportPdfJob;
use App\Models\Batch;
use App\Models\Collection;
use App\Models\Item;
use App\Services\CaptureService;
use App\Services\CollectionService;
use App\Services\ProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BulkCaptureController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('BulkCapture', [
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    /**
     * Create one item from a group of photographs. The bulk screen calls
     * this once per collectible, so grouping stays under the user's control.
     * The first item of a session creates the batch; later calls reuse it.
     */
    public function store(StoreBulkItemRequest $request, CaptureService $capture, CollectionService $collections): JsonResponse
    {
        $batchId = $request->integer('batch_id') ?: null;

        if ($batchId === null) {
            $batchId = Batch::create(['user_id' => $request->user()->id, 'source' => 'bulk'])->id;
        }

        $collectionId = $collections->resolveFromRequest($request, $request->user());

        $item = null;

        foreach ($request->file('photos') as $photo) {
            $item = $capture->storeImage($request->user(), $item, $photo, $batchId, $collectionId);
        }

        /** @var Item $item */
        return response()->json([
            'itemId' => $item->id,
            'batchId' => $batchId,
            'collectionId' => $collectionId,
            'photos' => $item->images()->count(),
        ], 201);
    }

    /**
     * Accept a scanned PDF (pages in front/back order) and convert it into
     * items in the background.
     */
    public function storePdf(Request $request, CollectionService $collections): JsonResponse
    {
        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:204800'],
            'photos_per_item' => ['required', 'integer', 'in:1,2'],
            ...CollectionService::rules(),
        ]);

        $path = $request->file('pdf')->store('imports', 'local');

        $batch = Batch::create([
            'user_id' => $request->user()->id,
            'source' => 'pdf',
            'label' => $request->file('pdf')->getClientOriginalName(),
        ]);

        $collectionId = $collections->resolveFromRequest($request, $request->user());

        ImportPdfJob::dispatch($path, (int) $validated['photos_per_item'], $request->user()->id, $batch->id, $collectionId);

        return response()->json([
            'batchId' => $batch->id,
            'label' => $batch->displayLabel(),
            'collectionId' => $collectionId,
            'message' => 'PDF received — converting into items.',
        ], 202);
    }

    /**
     * Live per-batch numbers for the bulk workspace.
     */
    public function status(Request $request): JsonResponse
    {
        $ids = collect(explode(',', $request->string('ids')->toString()))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->take(20)
            ->all();

        $batches = Batch::whereIn('id', $ids)
            ->withCount([
                'items',
                'items as captured_count' => fn ($query) => $query->where('status', Item::STATUS_CAPTURED),
                'items as in_progress_count' => fn ($query) => $query->whereIn('status', [Item::STATUS_QUEUED, Item::STATUS_PROCESSING]),
                'items as processed_count' => fn ($query) => $query->where('status', Item::STATUS_PROCESSED),
                'items as needs_review_count' => fn ($query) => $query->where('status', Item::STATUS_NEEDS_REVIEW),
            ])
            ->get();

        return response()->json([
            'batches' => $batches->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'label' => $batch->displayLabel(),
                'converting' => $batch->source === 'pdf' && $batch->converted_at === null,
                'itemCount' => $batch->items_count,
                'captured' => $batch->captured_count,
                'inProgress' => $batch->in_progress_count,
                'processed' => $batch->processed_count,
                'needsReview' => $batch->needs_review_count,
            ])->all(),
        ]);
    }

    /**
     * Process only the given batches' captured items.
     */
    public function processBatches(Request $request, ProcessingService $processing): JsonResponse
    {
        $validated = $request->validate([
            'batch_ids' => ['required', 'array', 'min:1', 'max:20'],
            'batch_ids.*' => ['integer', 'exists:batches,id'],
        ]);

        $count = $processing->queueBatches($validated['batch_ids']);

        return response()->json(['queued' => $count]);
    }
}
