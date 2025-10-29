<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;
use Modules\User\Http\Controllers\Api\UserPreferenceController;

/*
|--------------------------------------------------------------------------
| API Routes - User Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->prefix('api')->group(function () {

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
