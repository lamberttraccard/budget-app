<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\TransactionController;
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

Route::get('categories/{category}', [CategoryController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('categories.show');

Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('transactions.update');

require __DIR__.'/settings.php';
