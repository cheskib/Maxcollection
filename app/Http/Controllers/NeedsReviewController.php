<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Inertia\Inertia;
use Inertia\Response;

class NeedsReviewController extends Controller
{
    public function index(): Response
    {
        $items = Item::where('status', Item::STATUS_NEEDS_REVIEW)
            ->with(['metadata', 'images' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'thumbnailImageId' => $item->images->first()?->id,
                'thumbnailRotation' => $item->images->first()?->rotation ?? 0,
                'title' => $item->metadata?->primaryTitle() ?? "Item #{$item->id}",
                'reason' => $item->reviewReasonLabel() ?? 'Needs review',
                'confidence' => $item->metadata?->confidence,
                'processedAt' => $item->processed_at?->format('M j, Y g:i A'),
            ]);

        return Inertia::render('NeedsReview', ['items' => $items->all()]);
    }
}
