<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentItem extends Model
{
    public const STATIONS = ['comic_photo', 'card_scan', 'bagging', 'printing', 'everywhere'];

    public const STATUSES = ['have', 'ordered', 'need', 'later'];

    protected $fillable = ['station', 'name', 'note', 'status', 'price', 'links', 'sort_order'];

    protected function casts(): array
    {
        return ['links' => 'array'];
    }
}
