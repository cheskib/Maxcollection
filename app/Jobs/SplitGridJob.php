<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\Item;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Splits one overhead photo of a laid-out grid (1, 2, 4, or 6 equal boxes)
 * into individual items — one per cell, reading order. Comics use one
 * photo (front covers); cards add a second photo of the same grid with
 * every card flipped in place, and cells pair up by position.
 */
class SplitGridJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly string $frontPath,
        public readonly ?string $backPath,
        public readonly int $cells,
        public readonly int $userId,
        public readonly int $batchId,
        public readonly ?int $collectionId = null,
    ) {
    }

    public function handle(): void
    {
        $disk = Storage::disk('local');

        try {
            $fronts = $this->slice($disk->path($this->frontPath));
            $backs = $this->backPath !== null ? $this->slice($disk->path($this->backPath)) : null;

            foreach ($fronts as $index => $front) {
                $item = Item::create([
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId,
                    'collection_id' => $this->collectionId,
                ]);

                $this->storeCell($item, $front, $index, 'front');

                if ($backs !== null && isset($backs[$index])) {
                    $this->storeCell($item, $backs[$index], $index, 'back');
                }
            }

            Batch::whereKey($this->batchId)->update(['converted_at' => now()]);

            Log::info('Grid split complete', ['batch_id' => $this->batchId, 'cells' => $this->cells]);
        } catch (\Throwable $e) {
            Log::error('Grid split failed', ['batch_id' => $this->batchId, 'exception' => $e]);
            throw $e;
        } finally {
            $disk->delete($this->frontPath);

            if ($this->backPath !== null) {
                $disk->delete($this->backPath);
            }
        }
    }

    private function storeCell(Item $item, string $jpeg, int $index, string $role): void
    {
        $path = "original/{$item->id}/".Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        $item->images()->create([
            'path' => $path,
            'original_filename' => sprintf('grid-cell-%d-%s.jpg', $index + 1, $role),
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($jpeg),
            'role' => $role,
        ]);
    }

    /**
     * Cut the photo into equal cells, returned as JPEG strings in reading
     * order (left to right, top to bottom).
     *
     * @return array<int, string>
     */
    private function slice(string $file): array
    {
        $binary = (string) file_get_contents($file);
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException('The grid photo could not be read as an image.');
        }

        $source = $this->applyExifOrientation($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        [$rows, $columns] = $this->gridShape($width, $height);

        $cellWidth = intdiv($width, $columns);
        $cellHeight = intdiv($height, $rows);

        $cells = [];
        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $cell = imagecrop($source, [
                    'x' => $column * $cellWidth,
                    'y' => $row * $cellHeight,
                    'width' => $cellWidth,
                    'height' => $cellHeight,
                ]);

                if ($cell === false) {
                    throw new RuntimeException('A grid cell could not be cropped.');
                }

                ob_start();
                imagejpeg($cell, null, 92);
                $jpeg = (string) ob_get_clean();
                imagedestroy($cell);

                $cells[] = $jpeg;
            }
        }

        imagedestroy($source);

        return $cells;
    }

    /**
     * Phone cameras record orientation in EXIF instead of rotating pixels;
     * without this a portrait shot slices with the wrong grid shape.
     */
    private function applyExifOrientation(\GdImage $source, string $file): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $source;
        }

        $exif = @exif_read_data($file);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $source;
        }

        $rotated = imagerotate($source, $angle, 0);

        if ($rotated === false) {
            return $source;
        }

        imagedestroy($source);

        return $rotated;
    }

    /**
     * The row × column split for the cell count. Cards and comics are
     * portrait (roughly 0.7 wide per tall), so of the possible splits we
     * pick the one whose cells come closest to that shape — a landscape
     * photo of six boxes reads as 2×3, a portrait one as 3×2.
     *
     * @return array{int, int}
     */
    private function gridShape(int $width, int $height): array
    {
        $options = match ($this->cells) {
            1 => [[1, 1]],
            2 => [[1, 2], [2, 1]],
            4 => [[2, 2]],
            6 => [[2, 3], [3, 2]],
            default => throw new RuntimeException("Unsupported cell count: {$this->cells}."),
        };

        return collect($options)
            ->sortBy(function (array $shape) use ($width, $height) {
                [$rows, $columns] = $shape;
                $cellAspect = ($width / $columns) / ($height / $rows);

                return abs($cellAspect - 0.7);
            })
            ->first();
    }
}
