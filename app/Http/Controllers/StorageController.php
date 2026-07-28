<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\StorageBox;
use App\Models\StorageEvent;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    /**
     * The packing screen: one scan input drives the whole workflow.
     */
    public function index(Request $request, StorageService $storage): Response
    {
        $open = $storage->openBoxFor($request->user());

        $recentEvents = StorageEvent::with('barcode')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (StorageEvent $event) => [
                'id' => $event->id,
                'action' => match ($event->action) {
                    StorageEvent::BAG_ASSIGNED => 'Bag assigned to batch',
                    StorageEvent::BOX_OPENED => 'Box opened',
                    StorageEvent::BAG_ADDED => 'Bag added to box',
                    StorageEvent::DIVIDER_SCANNED => 'Divider scanned',
                    StorageEvent::SCAN_UNDONE => 'Scan undone',
                    StorageEvent::BOX_COMPLETED => 'Box completed',
                    default => $event->action,
                },
                'code' => $event->barcode?->code,
                'at' => $event->created_at->format('g:i:s A'),
            ])
            ->all();

        $boxes = StorageBox::with('barcode')
            ->where('status', StorageBox::STATUS_CLOSED)
            ->latest('closed_at')
            ->limit(20)
            ->get()
            ->map(fn (StorageBox $box) => [
                'id' => $box->id,
                'code' => $box->barcode->code,
                'closedAt' => $box->closed_at->format('M j, Y'),
                'bagCount' => $box->bag_count,
                'sectionCount' => $box->section_count,
                'cardCount' => $box->card_count,
            ])
            ->all();

        return Inertia::render('Storage', [
            'openBox' => $open === null ? null : $this->boxState($open),
            'boxes' => $boxes,
            'recentEvents' => $recentEvents,
        ]);
    }

    public function scan(Request $request, StorageService $storage): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);

        return back()->with('scan', $storage->scan($request->user(), $validated['code']));
    }

    public function undo(Request $request, StorageService $storage): RedirectResponse
    {
        return back()->with('scan', $storage->undoLastScan($request->user()));
    }

    public function complete(Request $request, StorageService $storage): RedirectResponse
    {
        return back()->with('scan', $storage->completeBox($request->user(), $request->boolean('confirmed')));
    }

    public function showBox(StorageBox $box): Response
    {
        $box->load(['barcode', 'sections.categoryBarcode', 'sections.batches.barcode']);

        return Inertia::render('StorageBox', [
            'box' => [
                'code' => $box->barcode->code,
                'status' => $box->status,
                'closedAt' => $box->closed_at?->format('M j, Y g:i A'),
                'bagCount' => $box->bag_count,
                'sectionCount' => $box->section_count,
                'cardCount' => $box->card_count,
                'sections' => $box->sections->map(fn ($section) => [
                    'position' => $section->position,
                    'category' => $section->categoryBarcode?->displayLabel(),
                    'categoryCode' => $section->categoryBarcode?->code,
                    'bags' => $section->batches->map(fn (Batch $batch) => [
                        'batchId' => $batch->id,
                        'code' => $batch->barcode->code,
                        'itemCount' => $batch->items()->count(),
                    ])->all(),
                ])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function boxState(StorageBox $box): array
    {
        $box->load(['barcode', 'sections.categoryBarcode']);
        $pending = $box->pendingSection();

        return [
            'id' => $box->id,
            'code' => $box->barcode->code,
            'pendingBagCount' => $pending?->batches()->count() ?? 0,
            'pendingPosition' => $pending?->position,
            'sections' => $box->sections
                ->filter(fn ($section) => $section->category_barcode_id !== null)
                ->values()
                ->map(fn ($section) => [
                    'position' => $section->position,
                    'category' => $section->categoryBarcode->displayLabel(),
                    'bagCount' => $section->batches()->count(),
                ])->all(),
        ];
    }
}
