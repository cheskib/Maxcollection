<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestFile extends Model
{
    protected $fillable = ['station_id', 'folder', 'filename', 'checksum', 'size_bytes', 'path', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
