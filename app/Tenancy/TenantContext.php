<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\Auth;

/**
 * Holds the organization the current request or job acts for.
 *
 * Web requests resolve it from the signed-in user; API requests and
 * background work set it explicitly. Registered as a scoped singleton,
 * so it never leaks between requests or queued jobs.
 */
final class TenantContext
{
    private ?int $organizationId = null;

    public function set(int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function clear(): void
    {
        $this->organizationId = null;
    }

    public function id(): ?int
    {
        return $this->organizationId ?? Auth::user()?->organization_id;
    }
}
