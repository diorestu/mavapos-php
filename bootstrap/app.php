<?php

use App\Http\Middleware\EnsureSubscriptionAccess;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/signin');
        $middleware->redirectUsersTo('/');
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'subscription' => EnsureSubscriptionAccess::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'pakasir/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
