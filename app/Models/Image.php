<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $fillable = ['item_id', 'path', 'original_filename', 'mime_type', 'size_bytes', 'rotation'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
