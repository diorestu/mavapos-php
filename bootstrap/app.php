<?php

use App\Http\Middleware\EnsureSubscriptionAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\ForceApiJson;
use App\Http\Middleware\SetMobileBranch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/account-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', ForceApiJson::class);
        $middleware->redirectGuestsTo('/signin');
        $middleware->redirectUsersTo('/');
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'subscription' => EnsureSubscriptionAccess::class,
            'superadmin' => EnsureSuperAdmin::class,
            'mobile.branch' => SetMobileBranch::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'sumopod/webhook',
            'pakasir/webhook',
            'api/mobile/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $error): bool => $request->is('api/*', 'account/delete') || $request->expectsJson());
    })->create();
