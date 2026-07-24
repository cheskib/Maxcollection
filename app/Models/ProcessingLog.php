<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['processing_job_id', 'level', 'message'];

    public function processingJob(): BelongsTo
    {
        return $this->belongsTo(ProcessingJob::class);
    }
}
