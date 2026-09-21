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

        // Set TRUSTED_PROXIES (comma separated IPs, or * for a load balancer or
        // Cloudflare in front) so HTTPS, the client IP and rate limits are read correctly.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

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
