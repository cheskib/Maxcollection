<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BulkCaptureController;
use App\Http\Controllers\CaptureController;
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

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/capture', [CaptureController::class, 'create'])->name('capture.create');
    Route::get('/capture/bulk', [BulkCaptureController::class, 'create'])->name('capture.bulk');
    Route::post('/capture/bulk/items', [BulkCaptureController::class, 'store'])->name('capture.bulk.store');
    Route::post('/capture/bulk/pdf', [BulkCaptureController::class, 'storePdf'])->name('capture.bulk.pdf');
    Route::get('/capture/{item}', [CaptureController::class, 'show'])->whereNumber('item')->name('capture.show');
    Route::post('/capture/images', [CaptureController::class, 'storeImage'])->name('capture.images.store');
    Route::delete('/images/{image}', [CaptureController::class, 'destroyImage'])->name('images.destroy');
    Route::get('/images/{image}', [ImageController::class, 'show'])->name('images.show');
    Route::post('/images/{image}/rotate', [ImageController::class, 'rotate'])->name('images.rotate');
    Route::get('/thumbnails/{image}', [ThumbnailController::class, 'show'])->name('thumbnails.show');

    Route::post('/process', [ProcessController::class, 'store'])->name('process');

    Route::get('/items', [ProcessedItemsController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/edit', [EditItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{item}/metadata', [EditItemController::class, 'update'])->name('items.metadata.update');
    Route::post('/items/{item}/reprocess', [ReprocessController::class, 'store'])->name('items.reprocess');

    Route::get('/review', [NeedsReviewController::class, 'index'])->name('review.index');

    Route::get('/settings', function () {
        return Inertia::render('ComingSoon');
    })->name('settings');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
