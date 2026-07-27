<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Items Uploaded click-through: a summary of everything uploaded and
 * where it stands — counts and progress only, never a list of cards.
 */
class InventoryController extends Controller
{
    public function index(): Response
    {
        $byStatus = Item::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $batches = Batch::withCount([
            'items',
            'items as processed_count' => fn ($query) => $query->where('status', Item::STATUS_PROCESSED),
            'items as needs_review_count' => fn ($query) => $query->where('status', Item::STATUS_NEEDS_REVIEW),
        ])->orderByDesc('id')->get();

        $singles = Item::whereNull('batch_id');

        return Inertia::render('Inventory', [
            'pipeline' => [
                'waiting' => (int) ($byStatus[Item::STATUS_CAPTURED] ?? 0),
                'queued' => (int) ($byStatus[Item::STATUS_QUEUED] ?? 0),
                'processing' => (int) ($byStatus[Item::STATUS_PROCESSING] ?? 0),
                'processed' => (int) ($byStatus[Item::STATUS_PROCESSED] ?? 0),
                'needsReview' => (int) ($byStatus[Item::STATUS_NEEDS_REVIEW] ?? 0),
            ],
            'uploads' => [
                'batches' => $batches->map(fn (Batch $batch) => [
                    'id' => $batch->id,
                    'label' => $batch->displayLabel(),
                    'uploadedAt' => $batch->created_at->format('M j, Y'),
                    'itemCount' => $batch->items_count,
                    'doneCount' => $batch->processed_count + $batch->needs_review_count,
                ])->all(),
                'singleCount' => (clone $singles)->count(),
                'singleDoneCount' => (clone $singles)->whereIn('status', [Item::STATUS_PROCESSED, Item::STATUS_NEEDS_REVIEW])->count(),
            ],
        ]);
    }
}
