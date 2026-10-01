<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for plan features, e.g. ->middleware('feature:whatsapp').
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $feature = Feature::from($feature);

        if ($request->user()->organization->canUse($feature)) {
            return $next($request);
        }

        $message = "{$feature->label()} is not included in your plan.";

        return $request->user()->isAdmin()
            ? redirect()->route('settings.billing')->with('warning', $message.' Upgrade to use it.')
            : redirect()->route('dashboard')->with('warning', $message.' Ask your admin to upgrade.');
    }
}
