<?php

use Illuminate\Support\Facades\Route;
use Modules\Recommendation\Http\Controllers\Api\RecommendationController;
use Modules\Recommendation\Http\Controllers\Api\GameInteractionController;

/*
|--------------------------------------------------------------------------
| API Routes - Recommendation Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->prefix('api')->group(function () {

    // Recommendations endpoints
    Route::prefix('recommendations')->group(function () {
        Route::get('/', [RecommendationController::class, 'index'])->name('recommendations.index');
        Route::get('/stats', [RecommendationController::class, 'stats'])->name('recommendations.stats');
        Route::get('/similar/{gameId}', [RecommendationController::class, 'similar'])->name('recommendations.similar');
    });

    // Game interactions endpoints
    Route::prefix('games/{gameId}')->group(function () {
        Route::post('/like', [GameInteractionController::class, 'like'])->name('games.like');
        Route::post('/dislike', [GameInteractionController::class, 'dislike'])->name('games.dislike');
        Route::post('/favorite', [GameInteractionController::class, 'favorite'])->name('games.favorite');
        Route::post('/view', [GameInteractionController::class, 'view'])->name('games.view');
        Route::post('/skip', [GameInteractionController::class, 'skip'])->name('games.skip');
    });

    // Interaction history endpoints
    Route::prefix('interactions')->group(function () {
        Route::get('/history', [GameInteractionController::class, 'history'])->name('interactions.history');
        Route::get('/favorites', [GameInteractionController::class, 'favorites'])->name('interactions.favorites');
    });
});
