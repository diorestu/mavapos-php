<?php

use App\Http\Controllers\MobileAccountController;
use App\Http\Middleware\RequirePersonalAccessToken;
use Illuminate\Support\Facades\Route;

Route::match(['delete', 'post'], '/account/delete', MobileAccountController::class)
    ->middleware(['auth:sanctum', RequirePersonalAccessToken::class, 'throttle:5,1'])->name('account.delete');
