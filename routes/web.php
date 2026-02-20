<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('imports/preview', [ImportController::class, 'preview'])
    ->middleware(['auth', 'verified'])
    ->name('imports.preview');

Route::post('imports', [ImportController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('imports.store');

require __DIR__.'/settings.php';
