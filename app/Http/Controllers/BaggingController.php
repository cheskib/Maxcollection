<?php

namespace App\Http\Controllers;

use App\Models\BaggingEvent;
use App\Services\BaggingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BaggingController extends Controller
{
    public function index(Request $request, BaggingService $bagging): Response
    {
        $open = $bagging->openTicket($request->user());

        $today = BaggingEvent::where('user_id', $request->user()->id)
            ->whereDate('created_at', today());

        return Inertia::render('Bagging', [
            'open' => $open === null ? null : [
                'bagCode' => $open->batch?->barcode?->code,
                'verdict' => $open->verdict,
                'flagReason' => $open->batch?->capture_flag,
                'startedAt' => $open->created_at->toIso8601String(),
            ],
            'today' => [
                'done' => (clone $today)->where('action', BaggingEvent::BAG_DONE)->count(),
                'setAside' => (clone $today)->where('action', BaggingEvent::SET_ASIDE)->count(),
                'averageSeconds' => (int) round((clone $today)->where('action', BaggingEvent::BAG_DONE)->avg('seconds') ?? 0),
            ],
            'recent' => BaggingEvent::where('user_id', $request->user()->id)
                ->whereIn('action', [BaggingEvent::BAG_DONE, BaggingEvent::SET_ASIDE])
                ->with('batch.barcode')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (BaggingEvent $event) => [
                    'id' => $event->id,
                    'bagCode' => $event->batch?->barcode?->code ?? $event->batch?->displayLabel() ?? '—',
                    'action' => $event->action,
                    'seconds' => $event->seconds,
                    'at' => $event->created_at->format('g:i A'),
                ])->all(),
        ]);
    }

    public function scan(Request $request, BaggingService $bagging): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);

        return back()->with('scan', $bagging->scan($request->user(), $validated['code']));
    }

    /**
     * The laminated SET-ASIDE card, printable: scanning it is how a
     * bagger confirms a flagged bin left the line.
     */
    public function setAsideCard(): Response
    {
        $generator = new BarcodeGeneratorSVG;

        return Inertia::render('SetAsideCard', [
            'svg' => $generator->getBarcode('SET-ASIDE', BarcodeGeneratorSVG::TYPE_CODE_128, 3, 90),
        ]);
    }
}
