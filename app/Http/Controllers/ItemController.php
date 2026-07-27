<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Metadata;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function show(Item $item): Response
    {
        $metadata = $item->metadata;
        $lastJob = $item->processingJobs()->latest('id')->first();

        $fields = [];
        if ($metadata !== null && isset(Metadata::CATEGORY_FIELDS[$metadata->category])) {
            foreach (Metadata::CATEGORY_FIELDS[$metadata->category] as $field) {
                $fields[] = [
                    'name' => $field,
                    'label' => Metadata::FIELD_LABELS[$field],
                    'value' => $metadata->{$field},
                ];
            }
        }

        return Inertia::render('ItemDetail', [
            'item' => [
                'id' => $item->id,
                'status' => $item->status,
                'reviewReason' => $item->reviewReasonLabel(),
                'title' => $metadata?->primaryTitle() ?? "Item #{$item->id}",
                'category' => $metadata?->categoryLabel() ?? 'Not processed yet',
                'confidence' => $metadata?->confidence,
                'processedAt' => $item->processed_at?->format('M j, Y g:i A'),
                'images' => $item->images()->orderBy('id')->get(['id', 'original_filename', 'rotation'])->all(),
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
