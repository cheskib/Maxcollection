<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Item;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Photos Uploaded click-through: a photo health check — coverage
 * counts plus the short list of cards still missing a photo side.
 */
class PhotoSummaryController extends Controller
{
    public function index(): Response
    {
        $total = Image::count();
        $itemCount = Item::count();

        $byRole = Image::selectRaw("coalesce(role, 'unlabeled') as role, count(*) as total")
            ->groupBy('role')
            ->pluck('total', 'role');

        // Cards with a single photo are almost always missing their back.
        $singlePhotoItems = Item::has('images', '=', 1)
            ->with(['metadata', 'images'])
            ->orderBy('id')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'title' => $item->metadata?->primaryTitle() ?? "Item #{$item->id}",
                'thumbnailImageId' => $item->images->first()?->id,
                'thumbnailVersion' => $item->images->first()?->versionTag() ?? '0',
            ]);

        $scannerCount = Image::whereHas('item.batch', fn ($query) => $query->where('source', 'pdf'))->count();

        return Inertia::render('PhotoSummary', [
            'totals' => [
                'photos' => $total,
                'items' => $itemCount,
                'perItem' => $itemCount > 0 ? round($total / $itemCount, 1) : 0,
            ],
            'bySide' => [
                'front' => (int) ($byRole['front'] ?? 0),
                'back' => (int) ($byRole['back'] ?? 0),
                'detail' => (int) ($byRole['detail'] ?? 0),
                'unlabeled' => (int) ($byRole['unlabeled'] ?? 0),
            ],
            'singlePhotoItems' => $singlePhotoItems->all(),
            'bySource' => [
                'scanner' => $scannerCount,
                'phone' => $total - $scannerCount,
            ],
            'storageMb' => round((Image::sum('size_bytes') ?: 0) / 1048576, 1),
        ]);
    }
}
