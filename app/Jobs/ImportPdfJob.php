<?php

namespace App\Jobs;

use App\Models\Item;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Converts a scanned PDF into items: pages are rendered to JPEG in order
 * and grouped (front/back pairs by default) exactly like bulk photo capture.
 */
class ImportPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly string $pdfPath,
        public readonly int $photosPerItem,
        public readonly int $userId,
    ) {
    }

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $workDir = $disk->path('imports/work-'.Str::uuid());
        mkdir($workDir, 0755, true);

        try {
            $result = Process::timeout(540)->run([
                'pdftoppm', '-jpeg', '-r', '200', $disk->path($this->pdfPath), $workDir.'/page',
            ]);

            if (! $result->successful()) {
                throw new RuntimeException('PDF conversion failed: '.trim($result->errorOutput()));
            }

            $pages = glob($workDir.'/page*.jpg') ?: [];
            natsort($pages);
            $pages = array_values($pages);

            if ($pages === []) {
                throw new RuntimeException('The PDF contained no convertible pages.');
            }

            $created = 0;

            foreach (array_chunk($pages, $this->photosPerItem) as $group) {
                $item = Item::create(['user_id' => $this->userId]);

                foreach ($group as $index => $pagePath) {
                    $storagePath = "original/{$item->id}/".Str::uuid().'.jpg';
                    $disk->put($storagePath, file_get_contents($pagePath));

                    $item->images()->create([
                        'path' => $storagePath,
                        'original_filename' => 'scan-page-'.($created * $this->photosPerItem + $index + 1).'.jpg',
                        'mime_type' => 'image/jpeg',
                        'size_bytes' => filesize($pagePath) ?: 0,
                    ]);
                }

                $created++;
            }

            Log::info('PDF import complete', ['pdf' => $this->pdfPath, 'items_created' => $created]);
        } catch (\Throwable $e) {
            Log::error('PDF import failed', ['pdf' => $this->pdfPath, 'exception' => $e]);
            throw $e;
        } finally {
            foreach (glob($workDir.'/page*.jpg') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($workDir);
            $disk->delete($this->pdfPath);
        }
    }
}
