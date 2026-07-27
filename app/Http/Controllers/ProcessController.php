<?php

namespace App\Http\Controllers;

use App\Services\ProcessingService;
use Illuminate\Http\RedirectResponse;

class ProcessController extends Controller
{
    public function store(ProcessingService $service): RedirectResponse
    {
        $count = $service->queueUnprocessedItems();

        $message = $count === 0
            ? 'There are no unprocessed items.'
            : "{$count} item(s) queued for processing.";

        // Back to wherever the button was pressed (Home or Inventory).
        return back(fallback: route('home'))->with('status', $message);
    }
}
