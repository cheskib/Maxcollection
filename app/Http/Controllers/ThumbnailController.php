<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ThumbnailController extends Controller
{
    public function show(Image $image, ThumbnailService $thumbnails): BinaryFileResponse
    {
        $path = $thumbnails->pathFor($image);

        // Fall back to the original when a thumbnail cannot be generated.
        $path ??= Storage::disk('local')->exists($image->path) ? $image->path : null;

        abort_if($path === null, 404);

        return response()->file(Storage::disk('local')->path($path));
    }
}
