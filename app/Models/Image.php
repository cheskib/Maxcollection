<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $fillable = [
        'item_id', 'path', 'original_filename', 'mime_type', 'size_bytes', 'role',
        'rotation', 'tilt', 'crop_top', 'crop_right', 'crop_bottom', 'crop_left',
    ];

    protected $casts = ['tilt' => 'float'];

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
            $this->rotation, $this->tilt, $this->crop_top, $this->crop_right, $this->crop_bottom, $this->crop_left,
        ]);
    }

    public function hasCrop(): bool
    {
        return $this->crop_top || $this->crop_right || $this->crop_bottom || $this->crop_left;
    }

    public function hasTurn(): bool
    {
        return $this->rotation !== 0 || $this->tilt !== 0.0;
    }

    /**
     * Whether the displayed rendering differs from the original photo.
     */
    public function isAdjusted(): bool
    {
        return $this->hasTurn() || $this->hasCrop();
    }
}
