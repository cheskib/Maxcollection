<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $fillable = [
        'item_id', 'path', 'original_filename', 'mime_type', 'size_bytes', 'role',
        'rotation', 'crop_top', 'crop_right', 'crop_bottom', 'crop_left',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Cache-busting tag: changes whenever the displayed rendering changes.
     */
    public function versionTag(): string
    {
        return implode('-', [
            $this->rotation, $this->crop_top, $this->crop_right, $this->crop_bottom, $this->crop_left,
        ]);
    }

    public function hasCrop(): bool
    {
        return $this->crop_top || $this->crop_right || $this->crop_bottom || $this->crop_left;
    }
}
