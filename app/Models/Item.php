<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    public const STATUS_CAPTURED = 'captured';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected $fillable = ['user_id', 'batch_id', 'status', 'review_reason', 'processed_at'];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(Metadata::class);
    }

    public function metadataHistory(): HasMany
    {
        return $this->hasMany(MetadataHistory::class);
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    /**
     * Human-readable reason why this item needs review (PROJECT.md 17).
     */
    public function reviewReasonLabel(): ?string
    {
        return match ($this->review_reason) {
            'low_confidence' => 'Low AI confidence',
            'unsupported_category' => 'Unsupported category',
            'ai_failure' => 'AI processing failure',
            'missing_metadata' => 'Missing required metadata',
            'unreadable_photographs' => 'Unreadable photographs',
            default => $this->review_reason,
        };
    }
}
