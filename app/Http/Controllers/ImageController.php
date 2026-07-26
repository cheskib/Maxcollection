<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    /**
     * Stream an original photograph to the authenticated administrator.
     * Files live on the private local disk and are never publicly reachable.
     */
    public function show(Image $image): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return response()->file(Storage::disk('local')->path($image->path));
    }
}
