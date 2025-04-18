<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\AuthTokenMiddleware;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::middleware([AuthTokenMiddleware::class])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('out', [AuthController::class, 'logout']);
        Route::post('out_all', [AuthController::class, 'logoutAll']);
        Route::get('tokens', [AuthController::class, 'tokens']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});
