<?php

use App\Http\Middleware\EnsureSubscriptionAccess;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SetMobileBranch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/signin');
        $middleware->redirectUsersTo('/');
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'subscription' => EnsureSubscriptionAccess::class,
            'superadmin' => EnsureSuperAdmin::class,
            'mobile.branch' => SetMobileBranch::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'pakasir/webhook',
            'api/mobile/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
