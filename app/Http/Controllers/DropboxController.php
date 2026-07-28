<?php

namespace App\Http\Controllers;

use App\Jobs\ArchiveBatchJob;
use App\Models\Batch;
use App\Services\DropboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DropboxController extends Controller
{
    public function connect(DropboxService $dropbox): RedirectResponse
    {
        if (! $dropbox->configured()) {
            return redirect()->route('settings')->with('status', 'Add DROPBOX_APP_KEY and DROPBOX_APP_SECRET on the server first.');
        }

        return redirect()->away($dropbox->authorizeUrl());
    }

    public function callback(Request $request, DropboxService $dropbox): RedirectResponse
    {
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('settings')->with('status', 'Dropbox connection was cancelled.');
        }

        try {
            $dropbox->connect($request->string('code'));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('settings')->with('status', 'Dropbox connection failed — please try again.');
        }

        // Batches finalized before the connection existed get archived now.
        $queued = $this->archiveAllPending();

        return redirect()->route('settings')->with(
            'status',
            'Dropbox connected.'.($queued > 0 ? " Archiving {$queued} finalized batch(es) in the background." : ''),
        );
    }

    public function disconnect(DropboxService $dropbox): RedirectResponse
    {
        $dropbox->disconnect();

        return back()->with('status', 'Dropbox disconnected. Already-archived copies stay in your Dropbox.');
    }

    /**
     * Queue every finalized-but-unarchived batch — the recovery path
     * after upload failures, and the backfill after connecting.
     */
    public function archivePending(DropboxService $dropbox): RedirectResponse
    {
        if (! $dropbox->connected()) {
            return back()->with('status', 'Connect Dropbox first.');
        }

        $queued = $this->archiveAllPending();

        return back()->with('status', $queued > 0 ? "Archiving {$queued} batch(es) in the background." : 'Nothing waiting to archive.');
    }

    private function archiveAllPending(): int
    {
        $pending = Batch::whereNotNull('barcode_id')->whereNull('archived_at')->pluck('id');

        foreach ($pending as $batchId) {
            ArchiveBatchJob::dispatch($batchId);
        }

        return $pending->count();
    }
}
