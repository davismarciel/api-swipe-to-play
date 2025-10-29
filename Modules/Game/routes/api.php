<?php

use Illuminate\Support\Facades\Route;
use Modules\Game\Http\Controllers\Api\GameController;

/*
|--------------------------------------------------------------------------
| API Routes - Game Module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->prefix('api')->group(function () {

    // Games endpoints
    Route::get('/games', [GameController::class, 'index'])->name('games.index');
    Route::get('/games/{id}', [GameController::class, 'show'])->name('games.show');

    // Genres and Categories endpoints
    Route::get('/genres', [GameController::class, 'genres'])->name('genres.index');
    Route::get('/categories', [GameController::class, 'categories'])->name('categories.index');
});
