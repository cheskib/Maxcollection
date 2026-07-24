<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        // Placeholder values until the items/images tables exist (Milestones 4-5),
        // as allowed by PHASE_1 Milestone 3.
        return Inertia::render('Home', [
            'stats' => [
                'itemsCaptured' => 0,
                'itemsProcessed' => 0,
                'needsReview' => 0,
                'picturesUploaded' => 0,
            ],
        ]);
    }
}
