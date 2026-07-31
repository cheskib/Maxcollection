<?php

namespace App\Http\Controllers;

use App\Models\EquipmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The living floor-buildout list: admins add gear and flip statuses as
 * things arrive.
 */
class EquipmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Equipment', [
            'items' => EquipmentItem::orderBy('sort_order')->orderBy('id')->get()
                ->groupBy('station')
                ->map(fn ($group) => $group->map(fn (EquipmentItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'note' => $item->note,
                    'status' => $item->status,
                    'price' => $item->price,
                    'links' => $item->links ?? [],
                ])->values())
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'station' => ['required', Rule::in(EquipmentItem::STATIONS)],
            'name' => ['required', 'string', 'max:128'],
            'note' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(EquipmentItem::STATUSES)],
            'price' => ['nullable', 'string', 'max:32'],
            'url' => ['nullable', 'url', 'max:500'],
        ]);

        EquipmentItem::create([
            'station' => $validated['station'],
            'name' => trim($validated['name']),
            'note' => $validated['note'] ?? null,
            'status' => $validated['status'],
            'price' => $validated['price'] ?? null,
            'links' => filled($validated['url'] ?? null) ? [['label' => 'Link', 'url' => $validated['url']]] : null,
            'sort_order' => (int) EquipmentItem::where('station', $validated['station'])->max('sort_order') + 1,
        ]);

        return back()->with('status', 'Item added.');
    }

    public function update(Request $request, EquipmentItem $equipment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(EquipmentItem::STATUSES)],
        ]);

        $equipment->update(['status' => $validated['status']]);

        return back();
    }

    public function destroy(EquipmentItem $equipment): RedirectResponse
    {
        $equipment->delete();

        return back()->with('status', 'Item removed.');
    }
}
