<?php

use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->group(function (): void {
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::middleware(['web', 'auth:sanctum', 'subscription', 'mobile.branch'])->group(function (): void {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::get('/pos', [PosController::class, 'index']);
        Route::post('/shifts/start', [PosController::class, 'startShift']);
        Route::post('/shifts/close', [PosController::class, 'closeShift']);
        Route::post('/checkout', [PosController::class, 'checkout']);
    });
});
