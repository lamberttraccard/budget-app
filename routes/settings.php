<?php

use App\Http\Controllers\Settings\MerchantRulesController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/merchant-rules');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/merchant-rules', [MerchantRulesController::class, 'index'])->name('merchant-rules.index');
    Route::delete('settings/merchant-rules/{merchantRule}', [MerchantRulesController::class, 'destroy'])->name('merchant-rules.destroy');
    Route::post('settings/merchant-rules/{merchantRule}/apply', [MerchantRulesController::class, 'apply'])->name('merchant-rules.apply');
});
