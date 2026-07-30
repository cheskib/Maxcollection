<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IngestFile;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives scan-station files from the uploader agent. Idempotent by
 * checksum: the agent may retry the same file forever without creating
 * duplicates, and it only deletes its local copy on our confirmation.
 */
class IngestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Station $station */
        $station = $request->attributes->get('station');

        $validated = $request->validate([
            'folder' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'filename' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._-]+\.(jpg|jpeg)$/i'],
            'checksum' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'file' => ['required', 'file', 'max:25600'],
        ]);

        // The agent computed the checksum before sending; verifying it here
        // guarantees the bytes survived the trip intact.
        $actual = hash_file('sha256', $request->file('file')->getRealPath());

        if (! hash_equals(strtolower($validated['checksum']), (string) $actual)) {
            return response()->json(['message' => 'Checksum mismatch — file corrupted in transit.'], 422);
        }

        $existing = IngestFile::where('station_id', $station->id)
            ->where('checksum', strtolower($validated['checksum']))
            ->first();

        if ($existing !== null) {
            // Already safely stored; tell the agent so it can move on.
            return response()->json(['status' => 'duplicate'], 200);
        }

        $path = $request->file('file')->storeAs(
            sprintf('ingest/%d/%s', $station->id, $validated['folder']),
            $validated['filename'],
            'local',
        );

        IngestFile::create([
            'station_id' => $station->id,
            'folder' => $validated['folder'],
            'filename' => $validated['filename'],
            'checksum' => strtolower($validated['checksum']),
            'size_bytes' => $request->file('file')->getSize() ?: 0,
            'path' => $path,
        ]);

        // Debounced folder processing: every file schedules a check; the
        // one scheduled by the folder's last file finds it quiet and acts.
        \App\Jobs\ProcessIngestFolderJob::dispatch($station->id, $validated['folder'])
            ->delay(now()->addSeconds(\App\Jobs\ProcessIngestFolderJob::DELAY_SECONDS));

        return response()->json(['status' => 'stored'], 201);
    }
}
