<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\ProcessingService;
use Illuminate\Http\RedirectResponse;

class ReprocessController extends Controller
{
    public function store(Item $item, ProcessingService $service): RedirectResponse
    {
        $service->queueItem($item);

        return redirect()
            ->route('items.show', $item)
            ->with('status', 'Item queued for reprocessing.');
    }
}
