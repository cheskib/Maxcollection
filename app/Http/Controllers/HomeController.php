<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        // Collection-wide value: each card's Our Value, falling back to
        // its AI Ballpark, plus how many cards carry a value at all.
        $value = DB::table('metadata')
            ->selectRaw('sum(coalesce(value_from, ai_value_from)) as value_from')
            ->selectRaw('sum(coalesce(value_to, ai_value_to)) as value_to')
            ->selectRaw('sum(case when coalesce(value_from, ai_value_from) is not null or coalesce(value_to, ai_value_to) is not null then 1 else 0 end) as valued_count')
            ->first();

        return Inertia::render('Home', [
            'stats' => [
                'itemsCaptured' => Item::count(),
                'itemsProcessed' => Item::where('status', Item::STATUS_PROCESSED)->count(),
                'needsReview' => Item::where('status', Item::STATUS_NEEDS_REVIEW)->count(),
                'picturesUploaded' => Image::count(),
                'unprocessed' => Item::where('status', Item::STATUS_CAPTURED)->count(),
                'value' => [
                    'from' => $value->value_from !== null ? round((float) $value->value_from, 2) : null,
                    'to' => $value->value_to !== null ? round((float) $value->value_to, 2) : null,
                    'valuedCount' => (int) ($value->valued_count ?? 0),
                ],
            ],
        ]);
    }
}
