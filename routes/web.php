<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\BulkCaptureController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\EditItemController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NeedsReviewController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProcessedItemsController;
use App\Http\Controllers\ReprocessController;
use App\Http\Controllers\ThumbnailController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// The branded front door: guests see the landing page; signed-in users
// go straight to the functional home.
Route::get('/', function () {
    return auth()->check()
        ? app()->call([app(HomeController::class), 'index'])
        : Inertia::render('Landing');
})->name('home');

Route::middleware('auth')->group(function () {

    Route::get('/capture', [CaptureController::class, 'create'])->name('capture.create');
    Route::get('/capture/bulk', [BulkCaptureController::class, 'create'])->name('capture.bulk');
    Route::post('/capture/bulk/items', [BulkCaptureController::class, 'store'])->name('capture.bulk.store');
    Route::post('/capture/bulk/pdf', [BulkCaptureController::class, 'storePdf'])->name('capture.bulk.pdf');
    Route::get('/capture/bulk/status', [BulkCaptureController::class, 'status'])->name('capture.bulk.status');
    Route::post('/capture/bulk/process', [BulkCaptureController::class, 'processBatches'])->name('capture.bulk.process');
    Route::post('/items/{item}/autograph', [CaptureController::class, 'setAutograph'])->name('items.autograph');
    Route::get('/capture/{item}', [CaptureController::class, 'show'])->whereNumber('item')->name('capture.show');
    Route::post('/capture/images', [CaptureController::class, 'storeImage'])->name('capture.images.store');
    Route::delete('/images/{image}', [CaptureController::class, 'destroyImage'])->name('images.destroy');
    Route::get('/images/{image}', [ImageController::class, 'show'])->name('images.show');
    Route::post('/images/{image}/rotate', [ImageController::class, 'rotate'])->name('images.rotate');
    Route::post('/images/{image}/undo', [ImageController::class, 'undoAdjustments'])->name('images.undo');
    Route::get('/images/{image}/trim', [ImageController::class, 'trimForm'])->name('images.trim');
    Route::post('/images/{image}/trim', [ImageController::class, 'trim'])->name('images.trim.save');
    Route::get('/thumbnails/{image}', [ThumbnailController::class, 'show'])->name('thumbnails.show');

    Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('inventory');
    Route::get('/items/summary', [\App\Http\Controllers\ProcessedSummaryController::class, 'index'])->name('items.summary');
    Route::get('/photos', [\App\Http\Controllers\PhotoSummaryController::class, 'index'])->name('photos');
    Route::post('/process', [ProcessController::class, 'store'])->name('process');
    Route::post('/reprocess-all', [\App\Http\Controllers\ReprocessController::class, 'all'])->name('reprocess.all');
    Route::post('/revalue-all', [\App\Http\Controllers\ReprocessController::class, 'values'])->name('revalue.all');

    Route::get('/items', [ProcessedItemsController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::put('/items/{item}/collection', [CollectionController::class, 'assignItem'])->name('items.collection');
    Route::get('/items/{item}/edit', [EditItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{item}/metadata', [EditItemController::class, 'update'])->name('items.metadata.update');
    Route::post('/items/{item}/reprocess', [ReprocessController::class, 'store'])->name('items.reprocess');

    Route::get('/review', [NeedsReviewController::class, 'index'])->name('review.index');

    Route::get('/browse', [BrowseController::class, 'index'])->name('browse');

    Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
    Route::get('/sets', [\App\Http\Controllers\CardSetController::class, 'index'])->name('sets.index');
    Route::get('/sets/{cardSet}', [\App\Http\Controllers\CardSetController::class, 'show'])->name('sets.show');
    Route::put('/sets/{cardSet}', [\App\Http\Controllers\CardSetController::class, 'update'])->name('sets.update');
    Route::get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
    Route::post('/batches/{batch}/bag', [BatchController::class, 'assignBag'])->name('batches.bag');

    Route::get('/storage', [\App\Http\Controllers\StorageController::class, 'index'])->name('storage');
    Route::post('/storage/scan', [\App\Http\Controllers\StorageController::class, 'scan'])->name('storage.scan');
    Route::post('/storage/undo', [\App\Http\Controllers\StorageController::class, 'undo'])->name('storage.undo');
    Route::post('/storage/complete', [\App\Http\Controllers\StorageController::class, 'complete'])->name('storage.complete');
    Route::get('/storage/labels', [\App\Http\Controllers\LabelController::class, 'form'])->name('labels');
    Route::post('/storage/labels', [\App\Http\Controllers\LabelController::class, 'generate'])->name('labels.generate');
    Route::get('/storage/labels/{run}', [\App\Http\Controllers\LabelController::class, 'print'])->name('labels.print');
    Route::get('/storage/boxes/{box}', [\App\Http\Controllers\StorageController::class, 'showBox'])->name('storage.box');
    Route::post('/batches/{batch}/collection', [BatchController::class, 'assignCollection'])->name('batches.collection');
    Route::post('/batches/{batch}/reprocess', [BatchController::class, 'reprocess'])->name('batches.reprocess');
    Route::delete('/batches/{batch}', [BatchController::class, 'destroy'])->name('batches.destroy');

    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');

    Route::get('/duplicates', [\App\Http\Controllers\DuplicatesController::class, 'index'])->name('duplicates');
    Route::post('/items/{item}/market-value', [\App\Http\Controllers\MarketValueController::class, 'store'])->name('items.market');
    Route::post('/items/bulk-edit', [\App\Http\Controllers\BulkEditController::class, 'update'])->name('items.bulk');
    Route::get('/export', [\App\Http\Controllers\ExportController::class, 'csv'])->name('export');
    Route::get('/stats', [\App\Http\Controllers\StatsController::class, 'index'])->name('stats');
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/key-names', [\App\Http\Controllers\SettingsController::class, 'addKeyName'])->name('settings.keynames.add');
    Route::delete('/settings/key-names/{keyName}', [\App\Http\Controllers\SettingsController::class, 'removeKeyName'])->name('settings.keynames.remove');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
