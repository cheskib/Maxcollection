<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Item;
use App\Services\MetadataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bulk edit: set the same field values across many cards at once. Each
 * change goes through MetadataService so per-card history is recorded
 * exactly as with single edits.
 */
class BulkEditController extends Controller
{
    public function update(Request $request, MetadataService $service): RedirectResponse
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1', 'max:200'],
            'item_ids.*' => ['integer', 'exists:items,id'],
            'fields' => ['array'],
            'fields.sport' => ['nullable', 'string', 'max:255'],
            'fields.team' => ['nullable', 'string', 'max:255'],
            'fields.year' => ['nullable', 'string', 'max:255'],
            'fields.manufacturer' => ['nullable', 'string', 'max:255'],
            'fields.card_type' => ['nullable', 'string', 'max:255'],
            'fields.rookie_card' => ['nullable', Rule::in(['Yes', 'No'])],
            'fields.autograph' => ['nullable', Rule::in(['Yes', 'No'])],
            'collection_id' => ['nullable', 'string'],
        ]);

        $fields = collect($validated['fields'] ?? [])
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->all();

        // Metadata edits only apply to items that have been through
        // processing; MetadataService marks the item corrected.
        $items = Item::whereIn('id', $validated['item_ids'])
            ->whereIn('status', [Item::STATUS_PROCESSED, Item::STATUS_NEEDS_REVIEW])
            ->get();

        if ($fields !== []) {
            foreach ($items as $item) {
                $service->update(
                    $item,
                    $request->user(),
                    $item->metadata?->category ?? 'sports_card',
                    $fields,
                );
            }
        }

        // Optional collection move, applied to every selected item.
        $collection = $validated['collection_id'] ?? null;

        if ($collection !== null && $collection !== '') {
            $collectionId = $collection === 'unassigned'
                ? null
                : Collection::findOrFail((int) $collection)->id;

            Item::whereIn('id', $validated['item_ids'])->update(['collection_id' => $collectionId]);
        }

        return back()->with('status', count($validated['item_ids']).' item(s) updated.');
    }
}
