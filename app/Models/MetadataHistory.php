<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetadataHistory extends Model
{
    protected $table = 'metadata_history';

    // Append-only records carry only created_at (PROJECT.md section 16).
    public const UPDATED_AT = null;

    protected $fillable = ['item_id', 'user_id', 'field_name', 'previous_value', 'new_value'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
