<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the displayed form of a photograph: rotation, then edge trims,
 * then optional downscaling. The original file is never modified
 * (PROJECT.md rule 6); every consumer (thumbnails, full view, AI) goes
 * through here so they always agree.
 */
class ImageRenderService
{
    public function render(Image $image, ?int $maxEdge = null, bool $applyCrop = true, int $quality = 85, bool $applyRotation = true): ?string
    {
        $binary = Storage::disk('local')->get($image->path);

        if ($binary === null) {
            return null;
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        if ($applyRotation && $image->rotation) {
            $rotated = imagerotate($source, -$image->rotation, 0);

            if ($rotated !== false) {
                imagedestroy($source);
                $source = $rotated;
            }
        }

        if ($applyCrop && $image->hasCrop()) {
            $width = imagesx($source);
            $height = imagesy($source);

            $x = (int) round($width * $image->crop_left / 100);
            $y = (int) round($height * $image->crop_top / 100);
            $cropWidth = max(1, $width - $x - (int) round($width * $image->crop_right / 100));
            $cropHeight = max(1, $height - $y - (int) round($height * $image->crop_bottom / 100));

            $cropped = imagecrop($source, ['x' => $x, 'y' => $y, 'width' => $cropWidth, 'height' => $cropHeight]);

            if ($cropped !== false) {
                imagedestroy($source);
                $source = $cropped;
            }
        }

        if ($maxEdge !== null) {
            $width = imagesx($source);
            $height = imagesy($source);
            $longest = max($width, $height);

            if ($longest > $maxEdge) {
                $scale = $maxEdge / $longest;
                $resized = imagescale($source, (int) round($width * $scale), (int) round($height * $scale));

                if ($resized !== false) {
                    imagedestroy($source);
                    $source = $resized;
                }
            }
        }

        ob_start();
        imagejpeg($source, null, $quality);
        $jpeg = ob_get_clean();
        imagedestroy($source);

        return $jpeg === false || $jpeg === '' ? null : $jpeg;
    }
}
