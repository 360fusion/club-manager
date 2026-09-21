<?php

use App\Http\Middleware\EnsureUserCanAdministerClub;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['club.admin' => EnsureUserCanAdministerClub::class]);

        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            // Self-activates on routes with a {clubSlug} parameter, so new
            // club routes are covered without having to remember this.
            EnsureUserCanAdministerClub::class,
        ]);

        // Third-party webhooks cannot present a CSRF token; they are verified
        // by signature in the controller instead.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
