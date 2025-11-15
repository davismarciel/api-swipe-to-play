<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;
use Modules\User\Http\Controllers\Api\UserPreferenceController;
use Modules\User\Http\Controllers\Api\OnboardingController;

/*
|--------------------------------------------------------------------------
| API Routes - User Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {

    // Onboarding endpoints
    Route::prefix('onboarding')->group(function () {
        Route::post('/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
        Route::get('/recommendations', [OnboardingController::class, 'getInitialRecommendations'])->name('onboarding.recommendations');
        Route::get('/status', [OnboardingController::class, 'checkStatus'])->name('onboarding.status');
    });

    // User preferences endpoints
    Route::prefix('user/preferences')->group(function () {
        Route::get('/', [UserPreferenceController::class, 'index'])->name('user.preferences.index');
        Route::put('/', [UserPreferenceController::class, 'updatePreferences'])->name('user.preferences.update');
        Route::put('/monetization', [UserPreferenceController::class, 'updateMonetizationPreferences'])->name('user.preferences.monetization');
        Route::put('/genres', [UserPreferenceController::class, 'updatePreferredGenres'])->name('user.preferences.genres');
        Route::put('/categories', [UserPreferenceController::class, 'updatePreferredCategories'])->name('user.preferences.categories');
    });
});

// Legacy routes
Route::prefix('v1')->group(function () {
    Route::get('test', [UserController::class, 'test']);
    Route::apiResource('users', UserController::class);
});
