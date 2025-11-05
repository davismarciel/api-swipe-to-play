<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

Route::prefix('v1/auth')->group(function () {
    // Rotas públicas (sem autenticação)
    Route::post('/login', [AuthController::class, 'login']);
    
    // Rota de refresh - permite tokens expirados (dentro do refresh_ttl)
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('jwt.refresh');
    
    // Rotas protegidas (requerem autenticação JWT válida)
    Route::middleware(['auth:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
