<?php

namespace App\Jobs;

use App\Models\Item;
use App\Services\AiService;
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
        public readonly ?int $batchId = null,
        public readonly ?int $collectionId = null,
    ) {
    }

    public function handle(AiService $ai): void
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
            $pageNumber = 0;

            foreach ($this->groupPages($pages, $ai) as $group) {
                $item = Item::create([
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId,
                    'collection_id' => $this->collectionId,
                ]);

                foreach ($group as $page) {
                    $pageNumber++;
                    $storagePath = "original/{$item->id}/".Str::uuid().'.jpg';
                    $disk->put($storagePath, file_get_contents($page['path']));

                    $item->images()->create([
                        'path' => $storagePath,
                        'original_filename' => "scan-page-{$pageNumber}.jpg",
                        'mime_type' => 'image/jpeg',
                        'size_bytes' => filesize($page['path']) ?: 0,
                        'role' => $page['role'],
                    ]);
                }

                $created++;
            }

            if ($this->batchId !== null) {
                \App\Models\Batch::whereKey($this->batchId)->update(['converted_at' => now()]);
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

    /**
     * Group pages into items. In front & back mode the AI labels each page
     * so a card whose back was not scanned still becomes its own item and
     * never steals the next card's front; when classification is
     * unavailable, pages fall back to mechanical pairs.
     *
     * @param array<int, string> $pages
     * @return array<int, array<int, array{path: string, role: string|null}>>
     */
    private function groupPages(array $pages, AiService $ai): array
    {
        $mechanical = fn (): array => array_map(
            fn (array $chunk) => array_map(fn (string $path) => ['path' => $path, 'role' => null], $chunk),
            array_chunk($pages, $this->photosPerItem),
        );

        if ($this->photosPerItem !== 2) {
            return $mechanical();
        }

        try {
            $roles = $ai->classifyPages(array_map(fn (string $path) => (string) file_get_contents($path), $pages));
        } catch (\Throwable $e) {
            Log::warning('Page classification failed; using mechanical pairs', ['exception' => $e]);
            $roles = null;
        }

        if ($roles === null) {
            return $mechanical();
        }

        $groups = [];
        $count = count($pages);

        for ($i = 0; $i < $count;) {
            if ($roles[$i] === 'front' && $i + 1 < $count && $roles[$i + 1] === 'back') {
                $groups[] = [
                    ['path' => $pages[$i], 'role' => 'front'],
                    ['path' => $pages[$i + 1], 'role' => 'back'],
                ];
                $i += 2;
            } else {
                // A front with no back behind it, or an orphaned back,
                // becomes a single-photo item.
                $groups[] = [['path' => $pages[$i], 'role' => $roles[$i]]];
                $i += 1;
            }
        }

        return $groups;
    }

}
