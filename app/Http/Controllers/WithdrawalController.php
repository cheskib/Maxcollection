<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Withdrawal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Documented removals and reinstatements — admin only (enforced by
 * route middleware). The item record is never deleted; disposition and
 * the withdrawal trail change instead.
 */
class WithdrawalController extends Controller
{
    public function store(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(Withdrawal::REASONS)],
            'notes' => ['required_if:reason,other', 'nullable', 'string', 'max:2000'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sale_date' => ['nullable', 'date'],
            'buyer' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'string', 'max:64'],
            'destination' => ['nullable', 'string', 'max:128'],
        ]);

        if ($item->disposition !== null) {
            return back()->with('status', 'This card is already removed — reinstate it first.');
        }

        $item->withdrawals()->create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        $item->update([
            'disposition' => in_array($validated['reason'], Withdrawal::GONE_REASONS, true)
                ? Item::DISPOSITION_GONE
                : Item::DISPOSITION_RELOCATED,
            'withdrawn_at' => now(),
        ]);

        return back()->with('status', 'Removal recorded.');
    }

    /**
     * The card returns to circulation using its original record —
     * physically to its original bag or any location noted here.
     */
    public function reinstate(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $withdrawal = $item->activeWithdrawal();

        if ($withdrawal === null) {
            return back()->with('status', 'This card is not removed.');
        }

        $withdrawal->update([
            'reinstated_at' => now(),
            'reinstated_by' => $request->user()->id,
            'reinstate_notes' => $validated['notes'] ?? null,
        ]);

        $item->update(['disposition' => null, 'withdrawn_at' => null]);

        return back()->with('status', 'Card reinstated.');
    }

    /**
     * A relocated card moved again (safe → box 7, grading → safe):
     * a fresh withdrawal row keeps every hop in the trail.
     */
    public function updateLocation(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'destination' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $withdrawal = $item->activeWithdrawal();

        if ($withdrawal === null || $item->disposition !== Item::DISPOSITION_RELOCATED) {
            return back()->with('status', 'Only a relocated card can change location.');
        }

        $withdrawal->update([
            'reinstated_at' => now(),
            'reinstated_by' => $request->user()->id,
            'reinstate_notes' => 'Moved on to: '.$validated['destination'],
        ]);

        $item->withdrawals()->create([
            'user_id' => $request->user()->id,
            'reason' => 'moved',
            'destination' => $validated['destination'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $item->update(['withdrawn_at' => now()]);

        return back()->with('status', 'Location updated.');
    }
}
