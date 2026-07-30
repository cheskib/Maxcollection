<?php

namespace App\Jobs;

use App\Models\Barcode;
use App\Models\Batch;
use App\Models\IngestFile;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Station;
use App\Models\User;
use App\Services\BarcodeReader;
use App\Services\ProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns a quiet ingest folder into a batch: ticket verified against the
 * folder, cards paired front/back in feed order, bag bound at capture
 * time, AI queued in the background. Problems FLAG the batch — the line
 * never stops and the scanning operator is never told (owner ruling).
 *
 * Every received file schedules one of these, delayed; the job only
 * acts once the folder has stopped receiving. The last file's job does
 * the work; earlier ones find it either still busy or already done.
 */
class ProcessIngestFolderJob implements ShouldQueue
{
    use Queueable;

    /** Seconds without a new file before a folder counts as complete. */
    public const QUIET_SECONDS = 20;

    /** Dispatch delay — comfortably past the quiet window. */
    public const DELAY_SECONDS = 25;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $stationId,
        public readonly string $folder,
    ) {
    }

    public function handle(BarcodeReader $reader, ProcessingService $processing): void
    {
        $station = Station::find($this->stationId);
        if ($station === null) {
            return;
        }

        $files = IngestFile::where('station_id', $station->id)
            ->where('folder', $this->folder)
            ->whereNull('processed_at')
            ->get();

        if ($files->isEmpty()) {
            return; // Another job already processed this folder.
        }

        // Still receiving: the newer file's own delayed job will handle it.
        if ($files->max('created_at')->gt(now()->subSeconds(self::QUIET_SECONDS))) {
            return;
        }

        $sorted = $files->sortBy('filename', SORT_NATURAL)->values();

        [$bagCode, $flag, $cardFiles] = $this->identifyTicket($reader, $sorted);

        $barcode = null;
        if ($bagCode !== null) {
            // A voided sticker (replaced after diagnosis) never returns.
            $barcode = Barcode::where('code', $bagCode)->where('type', Barcode::TYPE_BAG)
                ->whereNull('voided_at')->first();

            if ($barcode === null) {
                $flag = 'bag_unregistered';
            } elseif (Batch::where('barcode_id', $barcode->id)->exists()) {
                $flag = 'bag_conflict';
                $barcode = null;
            }
        }

        // Cards feed front, back, front, back — an odd count means a side
        // is missing somewhere. The orphan still becomes its own item so
        // no image is ever dropped (same rule as the PDF import).
        if ($cardFiles->count() % 2 !== 0) {
            $flag ??= 'missing_side';
        }

        $batch = Batch::create([
            'user_id' => $station->user_id ?? User::where('role', User::ROLE_ADMIN)->orderBy('id')->value('id'),
            'source' => 'scan',
            'label' => $this->folder,
            'station_id' => $station->id,
            'capture_flag' => $flag,
            'barcode_id' => $barcode?->id,
            'status' => $barcode !== null ? Batch::STATUS_CLOSED : Batch::STATUS_OPEN,
            'finalized_at' => $barcode !== null ? now() : null,
        ]);

        $collectionId = Setting::value('default_collection_id') !== null
            ? (int) Setting::value('default_collection_id')
            : null;

        foreach ($cardFiles->chunk(2) as $pair) {
            $item = Item::create([
                'user_id' => $batch->user_id,
                'batch_id' => $batch->id,
                'collection_id' => $collectionId,
            ]);

            foreach (array_values($pair->all()) as $side => $file) {
                $this->attachImage($item, $file, $side === 0 ? 'front' : 'back');
            }
        }

        // A rescan after diagnosis: earlier deleted attempts for this bag
        // link forward so both captures live on one page, never deleted.
        Batch::where('label', $this->folder)
            ->where('id', '!=', $batch->id)
            ->whereNotNull('resolution')
            ->whereNull('superseded_by_batch_id')
            ->update(['superseded_by_batch_id' => $batch->id]);

        $files->each(fn (IngestFile $file) => $file->update(['processed_at' => now()]));

        // AI follows the images, in the background (owner ruling); the
        // hold setting is respected inside the job itself.
        $processing->queueBatches([$batch->id]);

        // Bound to its bag already — archive to Dropbox (no-op offline).
        if ($barcode !== null) {
            ArchiveBatchJob::dispatch($batch->id);
        }

        Log::info('Ingest folder processed', [
            'station' => $station->name,
            'folder' => $this->folder,
            'items' => $batch->items()->count(),
            'flag' => $flag,
        ]);
    }

    /**
     * The first file should be the bag ticket. The physical sticker is
     * the truth: a readable ticket wins over the folder name (mismatch =
     * flag). An unreadable ticket falls back to the folder name, flagged
     * as unverified, and the first file is then treated as a card so
     * nothing is dropped on a wrong guess.
     *
     * @param \Illuminate\Support\Collection<int, IngestFile> $sorted
     * @return array{string|null, string|null, \Illuminate\Support\Collection<int, IngestFile>}
     */
    private function identifyTicket(BarcodeReader $reader, $sorted): array
    {
        $first = $sorted->first();
        $codes = $reader->read(Storage::disk('local')->path($first->path));
        $ticketCode = collect($codes)->first(fn (string $code) => preg_match('/^BAG-\d{6}$/', $code) === 1);

        if ($ticketCode !== null) {
            $flag = $ticketCode !== $this->folder && preg_match('/^BAG-\d{6}$/', $this->folder) === 1
                ? 'ticket_mismatch'
                : null;

            return [$ticketCode, $flag, $sorted->slice(1)->values()];
        }

        if (preg_match('/^BAG-\d{6}$/', $this->folder) === 1) {
            return [$this->folder, 'ticket_unverified', $sorted->values()];
        }

        return [null, 'no_bag', $sorted->values()];
    }

    private function attachImage(Item $item, IngestFile $file, string $role): void
    {
        $disk = Storage::disk('local');
        $destination = "original/{$item->id}/".Str::uuid().'.jpg';
        $disk->move($file->path, $destination);

        $item->images()->create([
            'path' => $destination,
            'original_filename' => $file->filename,
            'mime_type' => 'image/jpeg',
            'size_bytes' => $file->size_bytes,
            'role' => $role,
        ]);
    }
}
