<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyManager;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifies the organization behind a public API call from its
 * X-Api-Key header and makes it the tenant for the rest of the request.
 */
class AuthenticateApiKey
{
    public function __construct(
        private readonly ApiKeyManager $keys,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-Api-Key');
        $organization = $key !== '' ? $this->keys->findOrganization($key) : null;

        if ($organization === null) {
            return response()->json(['message' => 'Invalid or missing API key.'], 401);
        }

        $this->tenant->set($organization->id);
        $request->attributes->set('organization', $organization);

        return $next($request);
    }
}
