<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::prefix('v1/auth')->group(function () {
    // Rotas públicas (sem autenticação)
    Route::post('/login', [AuthController::class, 'login']);
    
    // Rotas protegidas (requerem autenticação JWT)
    Route::middleware(['auth:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
