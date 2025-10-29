<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

Route::prefix('v1')->group(function () {
    Route::get('test', [UserController::class, 'test']);
    Route::apiResource('users', UserController::class);
});