<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageBox extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id', 'barcode_id', 'status', 'closed_at',
        'bag_count', 'section_count', 'card_count',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function barcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(StorageSection::class)->orderBy('position');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The section bags are currently being scanned into: the open box's
     * highest-position section without a divider yet.
     */
    public function pendingSection(): ?StorageSection
    {
        return $this->sections()->whereNull('divider_barcode_id')->reorder()->orderByDesc('position')->first();
    }

    /**
     * Live contents — boxes are not sealed; what matters is what is in
     * them NOW. The *_count columns keep the as-completed snapshot for
     * history only.
     */
    public function currentBagCount(): int
    {
        return Batch::whereIn('storage_section_id', $this->sections()->pluck('id'))->count();
    }

    public function currentCardCount(): int
    {
        return Item::whereIn(
            'batch_id',
            Batch::whereIn('storage_section_id', $this->sections()->pluck('id'))->pluck('id'),
        )->present()->count();
    }
}
