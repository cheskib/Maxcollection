<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Services\ThumbnailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ImageController extends Controller
{
    /**
     * Stream a photograph to the authenticated administrator, applying the
     * saved display rotation. The original file is never modified.
     */
    public function show(Image $image): Response
    {
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        if ($image->rotation === 0) {
            return response()->file(Storage::disk('local')->path($image->path));
        }

        $source = @imagecreatefromstring(Storage::disk('local')->get($image->path));

        if ($source === false) {
            return response()->file(Storage::disk('local')->path($image->path));
        }

        $rotated = imagerotate($source, -$image->rotation, 0);

        if ($rotated !== false) {
            imagedestroy($source);
            $source = $rotated;
        }

        ob_start();
        imagejpeg($source, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);

        return response($jpeg, 200, ['Content-Type' => 'image/jpeg']);
    }

    /**
     * Turn the image a quarter turn clockwise per press.
     */
    public function rotate(Image $image, ThumbnailService $thumbnails): RedirectResponse
    {
        $image->update(['rotation' => ($image->rotation + 90) % 360]);

        $thumbnails->forget($image);

        return back();
    }
}
