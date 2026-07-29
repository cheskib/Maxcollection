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

    // Where the physical card is relative to its bag: null = in the bag,
    // relocated = still owned but elsewhere (safe, grading, another box),
    // gone = ownership ended (sold, gifted, lost).
    public const DISPOSITION_RELOCATED = 'relocated';

    public const DISPOSITION_GONE = 'gone';

    protected $fillable = ['user_id', 'batch_id', 'collection_id', 'status', 'review_reason', 'processed_at', 'disposition', 'withdrawn_at'];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** Still part of the collection (in a bag or relocated — not gone). */
    public function scopeOwned($query)
    {
        return $query->where(fn ($inner) => $inner
            ->whereNull('disposition')
            ->orWhere('disposition', '!=', self::DISPOSITION_GONE));
    }

    /** Physically inside its bag right now. */
    public function scopePresent($query)
    {
        return $query->whereNull('disposition');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
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

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /** The withdrawal currently in effect, if the card is out of its bag. */
    public function activeWithdrawal(): ?Withdrawal
    {
        return $this->disposition === null
            ? null
            : $this->withdrawals()->whereNull('reinstated_at')->latest('id')->first();
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
