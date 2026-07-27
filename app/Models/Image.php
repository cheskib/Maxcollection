<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    protected $fillable = [
        'item_id', 'path', 'original_filename', 'mime_type', 'size_bytes', 'role',
        'rotation', 'rotation_locked', 'tilt', 'crop_top', 'crop_right', 'crop_bottom', 'crop_left',
        'previous_adjustments',
    ];

    protected $casts = ['tilt' => 'float', 'rotation_locked' => 'boolean', 'previous_adjustments' => 'array'];

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

    /**
     * The current rotation/tilt/trim as an array, for undo snapshots.
     *
     * @return array<string, int|float>
     */
    public function adjustmentValues(): array
    {
        return [
            'rotation' => $this->rotation,
            'tilt' => $this->tilt,
            'crop_top' => $this->crop_top,
            'crop_right' => $this->crop_right,
            'crop_bottom' => $this->crop_bottom,
            'crop_left' => $this->crop_left,
        ];
    }
}
