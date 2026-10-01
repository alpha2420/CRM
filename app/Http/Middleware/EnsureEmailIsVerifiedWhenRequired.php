<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's "verified" check, applied only while email verification is
 * switched on (CRM_REQUIRE_EMAIL_VERIFICATION).
 */
class EnsureEmailIsVerifiedWhenRequired
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (! config('crm.require_email_verification')) {
            return $next($request);
        }

        return app(EnsureEmailIsVerified::class)->handle($request, $next, $redirectToRoute);
    }
}
