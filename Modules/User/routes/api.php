<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;
use Modules\User\Http\Controllers\Api\UserPreferenceController;
use Modules\User\Http\Controllers\Api\OnboardingController;


Route::middleware(['force.json', 'auth:api'])->group(function () {

    Route::prefix('onboarding')->group(function () {
        Route::get('/status', [OnboardingController::class, 'status'])->name('onboarding.status');
        Route::post('/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    });

    Route::prefix('user/preferences')
        ->middleware(['onboarding.completed'])
        ->group(function () {
            Route::get('/', [UserPreferenceController::class, 'index'])->name('user.preferences.index');
            Route::put('/', [UserPreferenceController::class, 'updatePreferences'])->name('user.preferences.update');
            Route::put('/monetization', [UserPreferenceController::class, 'updateMonetizationPreferences'])->name('user.preferences.monetization');
            Route::put('/genres', [UserPreferenceController::class, 'updatePreferredGenres'])->name('user.preferences.genres');
            Route::put('/categories', [UserPreferenceController::class, 'updatePreferredCategories'])->name('user.preferences.categories');
        });
});

Route::middleware(['force.json'])->prefix('v1')->group(function () {
    Route::get('test', [UserController::class, 'test']);
    Route::apiResource('users', UserController::class);
});
