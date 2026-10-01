<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a workspace out of the CRM when its trial or subscription has
 * ended, or when the platform owner has suspended it. Admins are sent to
 * billing to renew; agents are told to contact their admin.
 */
class EnsureOrganizationIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $user->organization;

        if ($organization->isSuspended()) {
            return response()->view('billing.blocked', ['reason' => 'suspended'], 403);
        }

        if (! $organization->isActive()) {
            return $user->isAdmin()
                ? redirect()->route('settings.billing')->with('warning', 'Your plan has ended. Choose a plan to continue using the CRM.')
                : response()->view('billing.blocked', ['reason' => 'expired'], 403);
        }

        return $next($request);
    }
}
