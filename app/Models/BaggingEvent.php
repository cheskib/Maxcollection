<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaggingEvent extends Model
{
    public const TICKET_SCANNED = 'ticket_scanned';

    public const BAG_DONE = 'bag_done';

    public const SET_ASIDE = 'set_aside';

    public const ALARM = 'alarm';

    public const VERDICT_GOOD = 'good';

    public const VERDICT_FLAGGED = 'flagged';

    protected $fillable = ['user_id', 'batch_id', 'action', 'verdict', 'seconds'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
