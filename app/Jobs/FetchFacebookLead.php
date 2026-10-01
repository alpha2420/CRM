<?php

namespace App\Jobs;

use App\Integrations\FacebookLeadAds;
use App\Models\Integration;
use App\Tenancy\OrganizationScope;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchFacebookLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $integrationId, public readonly string $leadgenId) {}

    public function handle(FacebookLeadAds $facebook, TenantContext $tenant): void
    {
        $integration = Integration::withoutGlobalScope(OrganizationScope::class)->find($this->integrationId);

        if ($integration === null || ! $integration->is_active) {
            return;
        }

        $tenant->set($integration->organization_id);
        $facebook->import($integration, $this->leadgenId);
    }
}
