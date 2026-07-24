<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/capture', [CaptureController::class, 'create'])->name('capture.create');
    Route::get('/capture/{item}', [CaptureController::class, 'show'])->name('capture.show');
    Route::post('/capture/images', [CaptureController::class, 'storeImage'])->name('capture.images.store');
    Route::delete('/images/{image}', [CaptureController::class, 'destroyImage'])->name('images.destroy');
    Route::get('/images/{image}', [ImageController::class, 'show'])->name('images.show');

    Route::get('/settings', function () {
        return Inertia::render('ComingSoon');
    })->name('settings');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
