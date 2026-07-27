<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Services\ImageRenderService;
use App\Services\ThumbnailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ImageController extends Controller
{
    /**
     * Stream a photograph to the authenticated administrator with its saved
     * rotation and trim applied. The original file is never modified.
     * ?uncropped=1 skips the trim (used by the trim screen's preview).
     */
    public function show(Request $request, Image $image, ImageRenderService $renderer): Response
    {
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        // ?original=1 serves the untouched upload (PROJECT.md 14: originals
        // shall always be viewable).
        if ($request->boolean('original')) {
            return response()->file(Storage::disk('local')->path($image->path));
        }

        $applyCrop = ! $request->boolean('uncropped');

        if (! $image->hasTurn() && ! ($applyCrop && $image->hasCrop())) {
            return response()->file(Storage::disk('local')->path($image->path));
        }

        $jpeg = $renderer->render($image, null, $applyCrop, 90);

        if ($jpeg === null) {
            return response()->file(Storage::disk('local')->path($image->path));
        }

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

    /**
     * Swap back to the cleanup that was in place before the last AI pass.
     * Swapping (instead of clearing) lets the user flip between the two.
     */
    public function undoAdjustments(Image $image, ThumbnailService $thumbnails): RedirectResponse
    {
        $previous = $image->previous_adjustments;

        abort_if($previous === null, 404);

        $image->update([
            'rotation' => (int) ($previous['rotation'] ?? 0),
            'tilt' => (float) ($previous['tilt'] ?? 0),
            'crop_top' => (int) ($previous['crop_top'] ?? 0),
            'crop_right' => (int) ($previous['crop_right'] ?? 0),
            'crop_bottom' => (int) ($previous['crop_bottom'] ?? 0),
            'crop_left' => (int) ($previous['crop_left'] ?? 0),
            'previous_adjustments' => $image->adjustmentValues(),
        ]);

        $thumbnails->forget($image);

        return back()->with('status', 'Previous cleanup restored.');
    }

    public function trimForm(Image $image): InertiaResponse
    {
        return Inertia::render('TrimImage', [
            'image' => [
                'id' => $image->id,
                'itemId' => $image->item_id,
                'version' => $image->versionTag(),
                'crop' => [
                    'top' => $image->crop_top,
                    'right' => $image->crop_right,
                    'bottom' => $image->crop_bottom,
                    'left' => $image->crop_left,
                ],
            ],
        ]);
    }

    public function trim(Request $request, Image $image, ThumbnailService $thumbnails): RedirectResponse
    {
        $validated = $request->validate([
            'top' => ['required', 'integer', 'min:0', 'max:45'],
            'right' => ['required', 'integer', 'min:0', 'max:45'],
            'bottom' => ['required', 'integer', 'min:0', 'max:45'],
            'left' => ['required', 'integer', 'min:0', 'max:45'],
        ]);

        $image->update([
            'crop_top' => $validated['top'],
            'crop_right' => $validated['right'],
            'crop_bottom' => $validated['bottom'],
            'crop_left' => $validated['left'],
        ]);

        $thumbnails->forget($image);

        return redirect()->route('items.show', $image->item_id)->with('status', 'Trim saved.');
    }
}
