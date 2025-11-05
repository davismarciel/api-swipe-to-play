<?php

use Illuminate\Support\Facades\Route;
use Modules\Recommendation\Http\Controllers\RecommendationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('recommendations', RecommendationController::class)->names('recommendation');
});
