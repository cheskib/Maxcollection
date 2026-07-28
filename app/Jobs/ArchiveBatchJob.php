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

        foreach ($images as $image) {
            if (! Storage::disk('local')->exists($image->path)) {
                Log::warning("Archive: missing file for image {$image->id} ({$image->path}); skipped.");

                continue;
            }

            $dropbox->upload(
                $this->dropboxPath($batch, $image),
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
     * /BAG-000123/item-42-front-107.jpg — readable, unique, and stable
     * across retries.
     */
    private function dropboxPath(Batch $batch, Image $image): string
    {
        $extension = strtolower(pathinfo($image->original_filename, PATHINFO_EXTENSION) ?: 'jpg');

        return sprintf('/%s/item-%d-%s-%d.%s', $batch->barcode->code, $image->item_id, $image->role ?? 'photo', $image->id, $extension);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning("Archive of batch {$this->batchId} failed at offset {$this->offset}: ".$exception?->getMessage());
    }
}
