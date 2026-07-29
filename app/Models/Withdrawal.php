<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One documented removal of a card from its bag — sale, relocation,
 * grading trip, loss. Append-only: reinstatements stamp the same row
 * rather than deleting it, so the full trail survives.
 */
class Withdrawal extends Model
{
    public const REASONS = ['sold', 'moved', 'grading', 'damaged', 'lost', 'gift', 'other'];

    /** Reasons that end ownership — the card leaves the collection's value. */
    public const GONE_REASONS = ['sold', 'lost', 'gift'];

    public const REASON_LABELS = [
        'sold' => 'Sold',
        'moved' => 'Moved to safer storage',
        'grading' => 'Sent for grading',
        'damaged' => 'Damaged',
        'lost' => 'Lost',
        'gift' => 'Gift',
        'other' => 'Other',
    ];

    protected $fillable = [
        'item_id', 'user_id', 'reason', 'notes',
        'sale_price', 'sale_date', 'buyer', 'platform', 'destination',
        'reinstated_at', 'reinstated_by', 'reinstate_notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'float',
            'sale_date' => 'date',
            'reinstated_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason] ?? $this->reason;
    }
}
