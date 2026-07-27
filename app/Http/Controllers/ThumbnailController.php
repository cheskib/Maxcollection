<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Services\ImageRenderService;
use App\Services\ThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ThumbnailController extends Controller
{
    public function show(Request $request, Image $image, ThumbnailService $thumbnails, ImageRenderService $renderer): Response
    {
        // ?original=1: a small untouched preview for before/after comparison.
        if ($request->boolean('original')) {
            $jpeg = $renderer->render($image, 400, applyCrop: false, quality: 75, applyRotation: false);

            if ($jpeg !== null) {
                return response($jpeg, 200, ['Content-Type' => 'image/jpeg']);
            }
        }

        $path = $thumbnails->pathFor($image);

        // Fall back to the original when a thumbnail cannot be generated.
        $path ??= Storage::disk('local')->exists($image->path) ? $image->path : null;

        abort_if($path === null, 404);

        return response()->file(Storage::disk('local')->path($path));
    }
}
