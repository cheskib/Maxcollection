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

        return redirect()->route('home')->with('status', $message);
    }
}
