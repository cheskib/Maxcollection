<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Item;
use App\Models\Metadata;
use App\Services\CaptureService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    /**
     * Delete an item with all of its photographs, metadata, and history.
     */
    public function destroy(Item $item, CaptureService $capture): RedirectResponse
    {
        $capture->deleteItem($item);

        return redirect()->route('items.index')->with('status', 'Item deleted.');
    }

    public function show(Item $item): Response
    {
        $metadata = $item->metadata;
        $lastJob = $item->processingJobs()->latest('id')->first();

        // Only the values that were actually pulled or entered; blanks are
        // noise here (the Edit screen still offers every field).
        $fields = [];
        if ($metadata !== null && isset(Metadata::CATEGORY_FIELDS[$metadata->category])) {
            foreach (Metadata::CATEGORY_FIELDS[$metadata->category] as $field) {
                if (filled($metadata->{$field})) {
                    $fields[] = [
                        'name' => $field,
                        'label' => Metadata::FIELD_LABELS[$field],
                        'value' => $metadata->{$field},
                    ];
                }
            }
        }

        return Inertia::render('ItemDetail', [
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
            'item' => [
                'id' => $item->id,
                'collectionId' => $item->collection_id,
                'status' => $item->status,
                'reviewReason' => $item->reviewReasonLabel(),
                'title' => $metadata?->primaryTitle() ?? "Item #{$item->id}",
                'category' => $metadata?->categoryLabel() ?? 'Not processed yet',
                'confidence' => $metadata?->confidence,
                'processedAt' => $item->processed_at?->format('M j, Y g:i A'),
                'images' => $item->images()->orderBy('id')->get()->map(fn ($image) => ['id' => $image->id, 'original_filename' => $image->original_filename, 'version' => $image->versionTag(), 'adjusted' => $image->isAdjusted()])->all(),
                'fields' => $fields,
                'processing' => $lastJob === null ? null : [
                    'status' => $lastJob->status,
                    'model' => $lastJob->model,
                    'error' => $lastJob->error_message,
                    'finishedAt' => $lastJob->finished_at?->format('M j, Y g:i A'),
                    'logs' => $lastJob->logs()->orderBy('id')->get()
                        ->map(fn ($log) => [
                            'level' => $log->level,
                            'message' => $log->message,
                            'at' => $log->created_at?->format('M j, Y g:i A'),
                        ])->all(),
                ],
            ],
        ]);
    }
}
