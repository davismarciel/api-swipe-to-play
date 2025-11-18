<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::middleware(['force.json'])->prefix('v1/auth')->group(function () {
    // Health check - no authentication required
    Route::get('/health', [AuthController::class, 'health'])->name('auth.health');
    
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('jwt.refresh');
    
    Route::middleware(['auth:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
