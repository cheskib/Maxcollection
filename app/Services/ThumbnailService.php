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
    private const MAX_WIDTH = 400;

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

        if (! $disk->exists($image->path)) {
            return null;
        }

        $source = @imagecreatefromstring($disk->get($image->path));

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min(self::MAX_WIDTH, $width);
        $targetHeight = (int) round($height * ($targetWidth / $width));

        $thumbnail = imagescale($source, $targetWidth, $targetHeight);
        imagedestroy($source);

        if ($thumbnail === false) {
            return null;
        }

        ob_start();
        imagejpeg($thumbnail, null, 80);
        $jpeg = ob_get_clean();
        imagedestroy($thumbnail);

        $disk->put($thumbnailPath, $jpeg);

        return $thumbnailPath;
    }
}
