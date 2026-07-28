<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Picqer\Barcode\BarcodeGeneratorSVG;

class LabelController extends Controller
{
    public function form(): Response
    {
        return Inertia::render('StorageLabels', [
            'counts' => [
                'bag' => Barcode::where('type', Barcode::TYPE_BAG)->count(),
                'box' => Barcode::where('type', Barcode::TYPE_BOX)->count(),
                'divider' => Barcode::where('type', Barcode::TYPE_DIVIDER)->count(),
            ],
        ]);
    }

    public function generate(Request $request, StorageService $storage): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([Barcode::TYPE_BAG, Barcode::TYPE_BOX, Barcode::TYPE_DIVIDER])],
            'count' => ['required_unless:type,divider', 'nullable', 'integer', 'min:1', 'max:200'],
            'names' => ['required_if:type,divider', 'nullable', 'string', 'max:5000'],
        ]);

        // Divider labels are named (one per line); bags and boxes are counted.
        $names = collect(explode("\n", $validated['names'] ?? ''))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values();

        $count = $validated['type'] === Barcode::TYPE_DIVIDER ? $names->count() : (int) $validated['count'];

        if ($count === 0) {
            return back()->withErrors(['names' => 'Enter at least one divider name.']);
        }

        $run = $storage->generateLabels($validated['type'], $count, $names->all());

        return redirect()->route('labels.print', ['run' => $run]);
    }

    public function print(string $run): Response
    {
        $generator = new BarcodeGeneratorSVG;

        $labels = Barcode::where('print_run', $run)
            ->orderBy('id')
            ->get()
            ->map(fn (Barcode $barcode) => [
                'code' => $barcode->code,
                'label' => $barcode->label,
                'svg' => $generator->getBarcode($barcode->code, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 48),
            ])
            ->all();

        abort_if($labels === [], 404);

        return Inertia::render('LabelPrint', ['labels' => $labels]);
    }
}
