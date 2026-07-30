<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Services\DiagnoseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scan-first diagnosis of flagged batches (admin only): scan the
 * set-aside bag, review both capture attempts, pick the mandatory
 * resolution.
 */
class DiagnoseController extends Controller
{
    public function index(): Response
    {
        // The waiting room: every flagged batch not yet resolved.
        $waiting = Batch::whereNotNull('capture_flag')
            ->whereNull('resolution')
            ->with(['barcode', 'station'])
            ->orderBy('id')
            ->get()
            ->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'bagCode' => $batch->barcode?->code ?? $batch->label,
                'flag' => $batch->capture_flag,
                'station' => $batch->station?->name,
                'capturedAt' => $batch->created_at->format('M j, g:i A'),
                'itemCount' => $batch->items()->count(),
            ]);

        return Inertia::render('Diagnose', ['waiting' => $waiting->all()]);
    }

    /** Scan the flagged bag → its diagnosis page. */
    public function scan(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $code = strtoupper((string) preg_replace('/\s+/', '', $validated['code']));

        $batch = Batch::whereNotNull('capture_flag')->whereNull('resolution')
            ->where(fn ($query) => $query
                ->whereHas('barcode', fn ($barcode) => $barcode->where('code', $code))
                ->orWhere('label', $code))
            ->first();

        if ($batch === null) {
            return back()->with('status', "No flagged batch found for {$code}.");
        }

        return redirect()->route('diagnose.show', $batch);
    }

    public function show(Batch $batch): Response
    {
        $attempts = Batch::where('label', $batch->label)
            ->where('id', '!=', $batch->id)
            ->whereNotNull('resolution')
            ->orderBy('id')
            ->get()
            ->map(fn (Batch $attempt) => $this->attemptSummary($attempt));

        return Inertia::render('DiagnoseBatch', [
            'batch' => [
                'id' => $batch->id,
                'bagCode' => $batch->barcode?->code ?? $batch->label,
                'bound' => $batch->barcode_id !== null,
                'flag' => $batch->capture_flag,
                'station' => $batch->station?->name,
                'capturedAt' => $batch->created_at->format('M j, Y g:i A'),
                'resolution' => $batch->resolution,
            ],
            'items' => $batch->items()->with(['images' => fn ($query) => $query->orderBy('id')])->orderBy('id')->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'images' => $item->images->map(fn ($image) => [
                        'id' => $image->id,
                        'role' => $image->role,
                        'version' => $image->versionTag(),
                    ])->all(),
                ])->all(),
            'priorAttempts' => $attempts->all(),
        ]);
    }

    private function attemptSummary(Batch $attempt): array
    {
        return [
            'id' => $attempt->id,
            'resolution' => $attempt->resolution,
            'note' => $attempt->resolution_note,
            'resolvedBy' => $attempt->resolved_by !== null ? \App\Models\User::find($attempt->resolved_by)?->name : null,
            'resolvedAt' => $attempt->resolved_at?->format('M j, Y g:i A'),
            'flag' => $attempt->capture_flag,
        ];
    }

    public function resolve(Request $request, Batch $batch, DiagnoseService $diagnose): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', Rule::in([
                DiagnoseService::RESOLUTION_CONFIRMED,
                DiagnoseService::RESOLUTION_RESCAN,
                DiagnoseService::RESOLUTION_REPLACED,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $message = $diagnose->resolve($request->user(), $batch, $validated['resolution'], $validated['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return redirect()->route('diagnose')->with('status', $message);
    }
}
