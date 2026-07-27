<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Collection;
use App\Models\Item;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BatchController extends Controller
{
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
                    'source' => $batch->source === 'pdf' ? 'Scanner PDF' : 'Bulk photos',
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
                'status' => match ($item->status) {
                    Item::STATUS_PROCESSED => 'Processed',
                    Item::STATUS_NEEDS_REVIEW => 'Needs Review',
                    default => 'Pending',
                },
                'reason' => $item->reviewReasonLabel(),
                'confidence' => $item->metadata?->confidence,
            ]);

        return Inertia::render('BatchDetail', [
            'batch' => [
                'id' => $batch->id,
                'label' => $batch->displayLabel(),
                'uploadedAt' => $batch->created_at->format('M j, Y g:i A'),
            ],
            'items' => $items->all(),
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }
}
