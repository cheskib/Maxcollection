<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id', 'source', 'label', 'content_hash', 'converted_at',
        'barcode_id', 'status', 'finalized_at', 'archived_at', 'storage_section_id',
    ];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'finalized_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function barcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class);
    }

    public function storageSection(): BelongsTo
    {
        return $this->belongsTo(StorageSection::class);
    }

    public function displayLabel(): string
    {
        // Once finalized, the bag barcode is the batch's permanent identity.
        return $this->barcode?->code ?? $this->label ?? "Batch #{$this->id}";
    }
}
