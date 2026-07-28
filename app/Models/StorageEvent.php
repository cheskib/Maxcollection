<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of every storage action — the record consulted when a
 * physical mistake needs to be located later.
 */
class StorageEvent extends Model
{
    public const BAG_ASSIGNED = 'bag_assigned';

    public const BOX_OPENED = 'box_opened';

    public const BAG_ADDED = 'bag_added';

    public const DIVIDER_SCANNED = 'divider_scanned';

    public const SCAN_UNDONE = 'scan_undone';

    public const BOX_COMPLETED = 'box_completed';

    protected $fillable = [
        'user_id', 'action', 'barcode_id',
        'storage_box_id', 'storage_section_id', 'batch_id',
    ];

    public function barcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
