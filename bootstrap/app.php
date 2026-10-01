<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'api.key' => AuthenticateApiKey::class,
            'subscribed' => EnsureOrganizationIsActive::class,
            'feature' => EnsureFeatureEnabled::class,
            'verified' => EnsureEmailIsVerifiedWhenRequired::class,
            'two-factor' => RequireTwoFactor::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        // The hosted lead form is public and runs inside other sites' iframes,
        // where session cookies are unavailable; it is protected by a
        // honeypot and rate limiting instead.
        $middleware->validateCsrfTokens(except: ['f/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
