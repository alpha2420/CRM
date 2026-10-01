<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a workspace requires two-factor login, members without it are sent
 * to set it up before they can use the CRM.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->organization->require_two_factor && ! $user->hasTwoFactor()) {
            return redirect()->route('security.show')->with('warning', 'Your workspace requires two-factor login. Set it up to continue.');
        }

        return $next($request);
    }
}
