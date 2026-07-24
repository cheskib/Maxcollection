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
        // itemsProcessed and needsReview stay at zero until processing exists
        // (Milestones 6-7), as allowed by PHASE_1 Milestone 3.
        return Inertia::render('Home', [
            'stats' => [
                'itemsCaptured' => Item::count(),
                'itemsProcessed' => 0,
                'needsReview' => 0,
                'picturesUploaded' => Image::count(),
            ],
        ]);
    }
}
