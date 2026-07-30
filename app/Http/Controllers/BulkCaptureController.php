<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBulkItemRequest;
use App\Jobs\ImportPdfJob;
use App\Jobs\SplitGridJob;
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
        // Batches still converting or holding unprocessed cards reappear in
        // the workspace, so navigating away never loses the Process button.
        $pending = Batch::withCount([
            'items',
            'items as captured_count' => fn ($query) => $query->where('status', Item::STATUS_CAPTURED),
            'items as in_progress_count' => fn ($query) => $query->whereIn('status', [Item::STATUS_QUEUED, Item::STATUS_PROCESSING]),
            'items as processed_count' => fn ($query) => $query->where('status', Item::STATUS_PROCESSED),
            'items as needs_review_count' => fn ($query) => $query->where('status', Item::STATUS_NEEDS_REVIEW),
        ])
            ->where(fn ($query) => $query
                ->where(fn ($converting) => $converting->whereIn('source', ['pdf', 'grid'])->whereNull('converted_at'))
                ->orWhereHas('items', fn ($items) => $items->where('status', Item::STATUS_CAPTURED)))
            ->orderBy('id')
            ->get();

        return Inertia::render('BulkCapture', [
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
            'pendingBatches' => $pending->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'label' => $batch->displayLabel(),
                'converting' => in_array($batch->source, ['pdf', 'grid'], true) && $batch->converted_at === null,
                'itemCount' => $batch->items_count,
                'captured' => $batch->captured_count,
                'inProgress' => $batch->in_progress_count,
                'processed' => $batch->processed_count,
                'needsReview' => $batch->needs_review_count,
            ])->all(),
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

        // Guardrail: the same PDF must not be uploaded twice — re-running
        // the AI is done with Reprocess Batch on the existing batch.
        $hash = hash_file('sha256', $request->file('pdf')->getRealPath());
        $existing = Batch::where('content_hash', $hash)->first();

        if ($existing !== null) {
            return response()->json([
                'message' => sprintf(
                    'This PDF was already uploaded as "%s" on %s. Open that batch and use Reprocess Batch instead.',
                    $existing->displayLabel(),
                    $existing->created_at->format('M j, Y'),
                ),
            ], 409);
        }

        // Guardrail: batch file names must be unique so every batch stays
        // unambiguously identifiable.
        $label = $request->file('pdf')->getClientOriginalName();
        $sameName = Batch::where('label', $label)->first();

        if ($sameName !== null) {
            return response()->json([
                'message' => sprintf(
                    'A batch named "%s" already exists (uploaded %s). Rename the file and try again.',
                    $label,
                    $sameName->created_at->format('M j, Y'),
                ),
            ], 409);
        }

        $path = $request->file('pdf')->store('imports', 'local');

        $batch = Batch::create([
            'user_id' => $request->user()->id,
            'source' => 'pdf',
            'label' => $label,
            'content_hash' => $hash,
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
     * Accept an overhead photo of a laid-out grid (1, 2, 4, or 6 boxes) and
     * split it into one item per cell in the background. A second photo of
     * the same grid, cards flipped in place, supplies the backs.
     */
    public function storeGrid(Request $request, CollectionService $collections): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:25600'],
            'back_photo' => ['nullable', 'image', 'max:25600'],
            'cells' => ['required', 'integer', 'in:1,2,4,6'],
            ...CollectionService::rules(),
        ]);

        // Guardrail: the same grid photo must not be uploaded twice —
        // re-running the AI is done with Reprocess Batch on the existing batch.
        $hash = hash_file('sha256', $request->file('photo')->getRealPath());
        $existing = Batch::where('content_hash', $hash)->first();

        if ($existing !== null) {
            return response()->json([
                'message' => sprintf(
                    'This photo was already uploaded as "%s" on %s. Open that batch and use Reprocess Batch instead.',
                    $existing->displayLabel(),
                    $existing->created_at->format('M j, Y'),
                ),
            ], 409);
        }

        $frontPath = $request->file('photo')->store('imports', 'local');
        $backPath = $request->file('back_photo')?->store('imports', 'local');

        $batch = Batch::create([
            'user_id' => $request->user()->id,
            'source' => 'grid',
            'content_hash' => $hash,
        ]);

        $collectionId = $collections->resolveFromRequest($request, $request->user());

        SplitGridJob::dispatch($frontPath, $backPath, (int) $validated['cells'], $request->user()->id, $batch->id, $collectionId);

        return response()->json([
            'batchId' => $batch->id,
            'label' => $batch->displayLabel(),
            'collectionId' => $collectionId,
            'message' => 'Photo received — splitting the grid into items.',
        ], 202);
    }

    /**
     * Live per-batch numbers for the bulk workspace.
     */
    public function status(Request $request, ProcessingService $processing): JsonResponse
    {
        // The polled status endpoint doubles as the stall rescuer, so a
        // stuck card heals itself while someone is watching the numbers.
        $processing->rescueStalledItems();

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
                'items as key_card_count' => fn ($query) => $query->whereHas('metadata', fn ($metadata) => $metadata->where('key_card', true)),
            ])
            ->get();

        return response()->json([
            'batches' => $batches->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'label' => $batch->displayLabel(),
                'converting' => in_array($batch->source, ['pdf', 'grid'], true) && $batch->converted_at === null,
                'itemCount' => $batch->items_count,
                'captured' => $batch->captured_count,
                'inProgress' => $batch->in_progress_count,
                'processed' => $batch->processed_count,
                'needsReview' => $batch->needs_review_count,
                'keyCards' => $batch->key_card_count,
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
