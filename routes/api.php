<?php

use Illuminate\Support\Facades\Route;

// Scan-station uploads: stateless, token-authenticated, no CSRF/session.
Route::middleware('station')->group(function () {
    Route::post('/ingest', [\App\Http\Controllers\Api\IngestController::class, 'store'])->name('api.ingest');
});
