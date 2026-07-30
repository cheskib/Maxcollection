<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\Image;
use App\Services\DropboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Copy a finalized batch's original images to Dropbox, named by the
 * permanent bag ID (rule §16: originals themselves are never touched —
 * this uploads byte-for-byte copies). Uploads run in chunks so each
 * queue run finishes well inside the worker timeout; the job re-queues
 * itself until every image is archived, then stamps archived_at.
 */
class ArchiveBatchJob implements ShouldQueue
{
    use Queueable;

    /** Images uploaded per queue run. */
    public const CHUNK = 40;

    // A failed run leaves the batch visibly "archive pending"; archiving
    // is re-triggered from Settings or the batch page, and overwrite-mode
    // uploads make any retry safe.
    public int $tries = 1;

    public int $timeout = 280;

    public function __construct(
        public readonly int $batchId,
        public readonly int $offset = 0,
    ) {
    }

    public function handle(DropboxService $dropbox): void
    {
        $batch = Batch::with('barcode')->find($this->batchId);

        // Deleted, never finalized, already archived, or disconnected —
        // nothing to do.
        if ($batch === null || $batch->barcode === null || $batch->archived_at !== null || ! $dropbox->connected()) {
            return;
        }

        $images = Image::whereHas('item', fn ($query) => $query->where('batch_id', $batch->id))
            ->orderBy('item_id')
            ->orderBy('id')
            ->skip($this->offset)
            ->take(self::CHUNK)
            ->get();

        // Files are renamed to the bag number: BAG-000123-01-front.jpg —
        // the ordinal is the card's position within the bag.
        $ordinals = $batch->items()->orderBy('id')->pluck('id')
            ->flip()
            ->map(fn ($index) => $index + 1);

        foreach ($images as $image) {
            if (! Storage::disk('local')->exists($image->path)) {
                Log::warning("Archive: missing file for image {$image->id} ({$image->path}); skipped.");

                continue;
            }

            $dropbox->upload(
                $this->dropboxPath($batch, $image, (int) $ordinals->get($image->item_id, 0)),
                Storage::disk('local')->get($image->path),
            );
        }

        if ($images->count() === self::CHUNK) {
            self::dispatch($this->batchId, $this->offset + self::CHUNK);

            return;
        }

        $batch->update(['archived_at' => now()]);
    }

    /**
     * /BAG-000123/BAG-000123-01-front.jpg — every file carries its bag
     * number even when separated from its folder. Unique and stable
     * across retries (the image id breaks any label tie).
     */
    private function dropboxPath(Batch $batch, Image $image, int $ordinal): string
    {
        $extension = strtolower(pathinfo($image->original_filename, PATHINFO_EXTENSION) ?: 'jpg');
        $bag = $batch->barcode->code;
        $label = $image->role ?? 'photo-'.$image->id;

        return sprintf('/%s/%s-%02d-%s.%s', $bag, $bag, $ordinal, $label, $extension);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning("Archive of batch {$this->batchId} failed at offset {$this->offset}: ".$exception?->getMessage());
    }
}
