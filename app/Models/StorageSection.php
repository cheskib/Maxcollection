<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageSection extends Model
{
    protected $fillable = ['storage_box_id', 'divider_barcode_id', 'position'];

    public function box(): BelongsTo
    {
        return $this->belongsTo(StorageBox::class, 'storage_box_id');
    }

    public function dividerBarcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class, 'divider_barcode_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
