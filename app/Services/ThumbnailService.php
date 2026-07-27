<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;

/**
 * Generates derived thumbnail images. Originals are never modified;
 * thumbnails are disposable and regenerated on demand (ARCHITECTURE.md 7).
 */
class ThumbnailService
{
    private const MAX_EDGE = 400;

    public function __construct(private readonly ImageRenderService $renderer)
    {
    }

    /**
     * Return the storage path of the thumbnail for an image, generating it
     * on first use. Returns null when the original cannot be read.
     */
    public function pathFor(Image $image): ?string
    {
        $thumbnailPath = "thumbnails/{$image->item_id}/{$image->id}.jpg";
        $disk = Storage::disk('local');

        if ($disk->exists($thumbnailPath)) {
            return $thumbnailPath;
        }

        $jpeg = $this->renderer->render($image, self::MAX_EDGE, quality: 80);

        if ($jpeg === null) {
            return null;
        }

        $disk->put($thumbnailPath, $jpeg);

        return $thumbnailPath;
    }

    /**
     * Drop the cached thumbnail (e.g. after rotation or trim) so it
     * regenerates.
     */
    public function forget(Image $image): void
    {
        Storage::disk('local')->delete("thumbnails/{$image->item_id}/{$image->id}.jpg");
    }
}
