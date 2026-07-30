<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Collection;
use App\Models\Item;
use App\Services\AiService;
use App\Services\CollectionService;
use App\Services\ProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BatchController extends Controller
{
    /**
     * Delete a batch with all of its items and files — used to clear
     * stuck or mistaken uploads.
     */
    public function destroy(Batch $batch, \App\Services\CaptureService $capture): RedirectResponse
    {
        $capture->deleteBatch($batch);

        return back(fallback: route('batches.index'))->with('status', 'Batch deleted.');
    }

    /**
     * Re-run the AI over every item in the batch.
     */
    public function reprocess(Request $request, Batch $batch, ProcessingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', Rule::in([AiService::SOURCE_CLEANED, AiService::SOURCE_ORIGINAL])],
        ]);

        $count = $service->reprocessBatch($batch, $validated['source'] ?? AiService::SOURCE_CLEANED);

        return back()->with('status', "{$count} item(s) queued for reprocessing.");
    }

    /**
     * Finalize the batch by scanning its bag barcode — the permanent ID.
     */
    public function assignBag(Request $request, Batch $batch, \App\Services\StorageService $storage): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);

        return back()->with('scan', $storage->assignBag($request->user(), $batch, $validated['code']));
    }

    /**
     * Take the bag out of its box — documented, admin-only. The bag is
     * loose afterward and can be scanned into another box normally.
     */
    public function removeFromBox(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $section = $batch->storageSection;
        if ($section === null) {
            return back()->with('status', 'This bag is not in a box.');
        }

        $boxCode = $section->box->barcode->code;
        $batch->update(['storage_section_id' => null]);

        \App\Models\StorageEvent::create([
            'user_id' => $request->user()->id,
            'action' => \App\Models\StorageEvent::BAG_REMOVED,
            'barcode_id' => $batch->barcode_id,
            'storage_box_id' => $section->storage_box_id,
            'storage_section_id' => $section->id,
            'batch_id' => $batch->id,
        ]);

        $note = filled($validated['notes'] ?? null) ? ' — '.$validated['notes'] : '';

        return back()->with('status', "Bag removed from box {$boxCode}{$note}. It can be packed into another box.");
    }

    /**
     * (Re)queue this batch's Dropbox archive — the per-batch recovery
     * path after a failed upload run.
     */
    public function archive(Batch $batch, \App\Services\DropboxService $dropbox): RedirectResponse
    {
        if (! $dropbox->connected() || $batch->barcode === null) {
            return back()->with('status', 'Archiving needs a connected Dropbox and a finalized batch.');
        }

        \App\Jobs\ArchiveBatchJob::dispatch($batch->id);

        return back()->with('status', 'Archiving to Dropbox in the background.');
    }

    /**
     * Move every item in the batch into a collection at once.
     */
    public function assignCollection(Request $request, Batch $batch, CollectionService $collections): RedirectResponse
    {
        $request->validate(CollectionService::rules());

        $batch->items()->update(['collection_id' => $collections->resolveFromRequest($request, $request->user())]);

        return back()->with('status', 'Batch moved.');
    }

    public function index(): Response
    {
        $batches = Batch::withCount([
            'items',
            'items as processed_count' => fn ($query) => $query->where('status', Item::STATUS_PROCESSED),
            'items as needs_review_count' => fn ($query) => $query->where('status', Item::STATUS_NEEDS_REVIEW),
            'items as pending_count' => fn ($query) => $query->whereIn('status', [
                Item::STATUS_CAPTURED, Item::STATUS_QUEUED, Item::STATUS_PROCESSING,
            ]),
        ])->orderByDesc('id')->get();

        // One aggregate query for processing timing across all batches.
        $timing = DB::table('processing_jobs')
            ->join('items', 'items.id', '=', 'processing_jobs.item_id')
            ->whereNotNull('items.batch_id')
            ->groupBy('items.batch_id')
            ->select('items.batch_id', DB::raw('MIN(processing_jobs.started_at) as first_started'), DB::raw('MAX(processing_jobs.finished_at) as last_finished'))
            ->get()
            ->keyBy('batch_id');

        $unbatchedCount = Item::whereNull('batch_id')->count();

        return Inertia::render('Batches', [
            'batches' => $batches->map(function (Batch $batch) use ($timing) {
                $times = $timing->get($batch->id);
                $duration = null;

                if ($times && $times->first_started && $times->last_finished && $batch->pending_count === 0) {
                    $seconds = strtotime($times->last_finished) - strtotime($times->first_started);
                    $duration = $seconds >= 60
                        ? intdiv($seconds, 60).'m '.($seconds % 60).'s'
                        : $seconds.'s';
                }

                return [
                    'id' => $batch->id,
                    'label' => $batch->displayLabel(),
                    'source' => match ($batch->source) {
                        'pdf' => 'Scanner PDF',
                        'grid' => 'Grid photo',
                        'scan' => 'Scan line',
                        default => 'Bulk photos',
                    },
                    'captureFlag' => $batch->capture_flag,
                    'uploadedAt' => $batch->created_at->format('M j, Y g:i A'),
                    'itemCount' => $batch->items_count,
                    'processedCount' => $batch->processed_count,
                    'needsReviewCount' => $batch->needs_review_count,
                    'pendingCount' => $batch->pending_count,
                    'processingTime' => $duration,
                ];
            })->all(),
            'unbatchedCount' => $unbatchedCount,
        ]);
    }

    public function show(Batch $batch): Response
    {
        $items = $batch->items()
            ->with(['metadata', 'images' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'thumbnailImageId' => $item->images->first()?->id,
                'thumbnailVersion' => $item->images->first()?->versionTag() ?? '0',
                'title' => $item->metadata?->primaryTitle() ?? "Item #{$item->id}",
                'disposition' => $item->disposition,
                'status' => match ($item->status) {
                    Item::STATUS_PROCESSED => 'Processed',
                    Item::STATUS_NEEDS_REVIEW => 'Needs Review',
                    default => 'Pending',
                },
                'reason' => $item->reviewReasonLabel(),
                'confidence' => $item->metadata?->confidence,
                'keyCard' => (bool) $item->metadata?->key_card,
            ]);

        // Physical location, when the finalized bag has been boxed.
        $section = $batch->storageSection?->load(['box.barcode', 'dividerBarcode']);

        return Inertia::render('BatchDetail', [
            'batch' => [
                'id' => $batch->id,
                'label' => $batch->displayLabel(),
                'uploadedAt' => $batch->created_at->format('M j, Y g:i A'),
                'pendingCount' => $batch->items()->whereIn('status', [
                    Item::STATUS_CAPTURED, Item::STATUS_QUEUED, Item::STATUS_PROCESSING,
                ])->count(),
                'bagCode' => $batch->barcode?->code,
                'captureFlag' => $batch->capture_flag,
                'finalizedAt' => $batch->finalized_at?->format('M j, Y g:i A'),
                'archivedAt' => $batch->archived_at?->format('M j, Y g:i A'),
                'archiveReady' => $batch->barcode !== null && $batch->archived_at === null
                    && app(\App\Services\DropboxService::class)->connected(),
                'location' => $section === null ? null : [
                    'box' => $section->box->barcode->code,
                    'boxId' => $section->box->id,
                    'section' => $section->dividerBarcode?->displayLabel() ?? 'No Divider Assigned',
                ],
            ],
            'items' => $items->all(),
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }
}
