<?php

use Illuminate\Support\Facades\Route;
use Modules\Recommendation\Http\Controllers\Api\RecommendationController;
use Modules\Recommendation\Http\Controllers\Api\GameInteractionController;

Route::middleware(['auth:api'])->group(function () {
    
    $recommendationRateLimit = config('recommendation.rate_limits.recommendations', '60,1');
    $interactionRateLimit = config('recommendation.rate_limits.interactions', '100,1');

    Route::prefix('recommendations')
        ->middleware(['throttle:' . $recommendationRateLimit])
        ->group(function () {
            Route::get('/', [RecommendationController::class, 'index'])->name('recommendations.index');
            Route::get('/stats', [RecommendationController::class, 'stats'])->name('recommendations.stats');
            Route::get('/similar/{gameId}', [RecommendationController::class, 'similar'])->name('recommendations.similar');
        });

    Route::prefix('games/{gameId}')
        ->middleware(['throttle:' . $interactionRateLimit])
        ->group(function () {
            Route::post('/like', [GameInteractionController::class, 'like'])->name('games.like');
            Route::post('/dislike', [GameInteractionController::class, 'dislike'])->name('games.dislike');
            Route::post('/favorite', [GameInteractionController::class, 'favorite'])->name('games.favorite');
            Route::post('/view', [GameInteractionController::class, 'view'])->name('games.view');
            Route::post('/skip', [GameInteractionController::class, 'skip'])->name('games.skip');
        });

    Route::delete('/interactions/clear', [GameInteractionController::class, 'clearAll'])
        ->name('interactions.clear');

});
