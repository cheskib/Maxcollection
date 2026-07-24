<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Item;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home', [
            'stats' => [
                'itemsCaptured' => Item::count(),
                'itemsProcessed' => Item::where('status', Item::STATUS_PROCESSED)->count(),
                'needsReview' => Item::where('status', Item::STATUS_NEEDS_REVIEW)->count(),
                'picturesUploaded' => Image::count(),
            ],
        ]);
    }
}
