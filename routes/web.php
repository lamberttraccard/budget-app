<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('home');

Route::post('imports/preview', [ImportController::class, 'preview'])
    ->middleware(['auth', 'verified'])
    ->name('imports.preview');

Route::post('imports', [ImportController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('imports.store');

Route::delete('imports/{import}', [ImportController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('imports.destroy');

Route::get('search', [SearchController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('search');

Route::get('recurring', [RecurringController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('recurring');

Route::get('categories/{category}', [CategoryController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('categories.show');

Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('transactions.update');

Route::get('manifest.webmanifest', function (): JsonResponse {
    $iconVersion = filemtime(public_path('icons/icon-192x192.png'));

    return response()->json([
        'name' => 'Budget',
        'short_name' => 'Budget',
        'description' => 'Personal budget management',
        'id' => '/',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'any',
        'background_color' => '#111113',
        'theme_color' => '#111113',
        'icons' => [
            ['src' => "/icons/icon-192x192.png?v={$iconVersion}", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => "/icons/icon-192x192.png?v={$iconVersion}", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => "/icons/icon-512x512.png?v={$iconVersion}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => "/icons/icon-512x512.png?v={$iconVersion}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
});

require __DIR__.'/settings.php';
