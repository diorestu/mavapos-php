<?php

use App\Http\Controllers\MobileAccountController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\Partner\ReportController;
use App\Http\Controllers\Partner\WebhookController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SaleRefundController;
use App\Http\Middleware\AuthenticatePartner;
use App\Http\Middleware\IdempotentCheckout;
use App\Http\Middleware\RequirePersonalAccessToken;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->group(function (): void {
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MobileAuthController::class, 'me'])->middleware(['web', 'mobile.branch']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::match(['delete', 'post'], '/account/delete', MobileAccountController::class)->middleware([RequirePersonalAccessToken::class, 'throttle:5,1'])->name('mobile.account.delete');
    });
    Route::post('/shifts/close', [PosController::class, 'closeShift'])
        ->middleware(['web', 'auth:sanctum', RequirePersonalAccessToken::class, 'mobile.branch', 'role:owner,admin,kasir']);
    Route::middleware(['web', 'auth:sanctum', 'subscription', 'mobile.branch'])->group(function (): void {
        Route::get('/pos', [PosController::class, 'index']);
        Route::post('/shifts/start', [PosController::class, 'startShift']);
        Route::post('/checkout', [PosController::class, 'checkout'])->middleware(IdempotentCheckout::class);
    });
    Route::middleware(['web', 'auth:sanctum', RequirePersonalAccessToken::class, 'subscription', 'mobile.branch', 'role:owner,admin'])->group(function (): void {
        Route::get('/sales/{sale}/refund', [SaleRefundController::class, 'show']);
        Route::post('/sales/{sale}/refund', [SaleRefundController::class, 'store']);
    });
});

foreach (['partner/v1', 'partner/sandbox/v1'] as $prefix) {
    Route::prefix($prefix)->middleware([AuthenticatePartner::class, 'throttle:partner'])->group(function (): void {
        Route::get('/webhook', [WebhookController::class, 'show']);
        Route::post('/webhook', [WebhookController::class, 'store']);
        Route::delete('/webhook', [WebhookController::class, 'destroy']);
        Route::post('/webhook/test', [WebhookController::class, 'test']);
        Route::get('/outlets', [ReportController::class, 'outlets']);
        Route::get('/products', [ReportController::class, 'products']);
        Route::get('/transactions', [ReportController::class, 'transactions']);
        Route::get('/transactions/{transaction}', [ReportController::class, 'transaction'])->whereNumber('transaction');
        Route::get('/sales-summary', [ReportController::class, 'summary']);
    });
}
