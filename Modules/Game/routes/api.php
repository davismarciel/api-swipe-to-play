<?php

use Illuminate\Support\Facades\Route;
use Modules\Game\Http\Controllers\Api\GameController;

Route::middleware(['force.json', 'auth:api'])->group(function () {
    Route::get('/games', [GameController::class, 'index'])->name('games.index');
    Route::get('/games/{id}', [GameController::class, 'show'])->name('games.show');

    Route::get('/genres', [GameController::class, 'genres'])->name('genres.index');
    Route::get('/categories', [GameController::class, 'categories'])->name('categories.index');
});
