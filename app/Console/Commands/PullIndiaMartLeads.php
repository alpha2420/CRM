<?php

namespace App\Console\Commands;

use App\Enums\IntegrationType;
use App\Integrations\IndiaMartLeads;
use App\Models\Integration;
use App\Tenancy\OrganizationScope;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:indiamart')]
#[Description('Fetch new IndiaMART enquiries for every connected workspace (at most every 5 minutes each)')]
class PullIndiaMartLeads extends Command
{
    public function handle(IndiaMartLeads $indiaMart, TenantContext $tenant): int
    {
        $added = 0;

        Integration::withoutGlobalScope(OrganizationScope::class)
            ->where('type', IntegrationType::IndiaMart)
            ->where('is_active', true)
            ->with('organization')
            ->each(function (Integration $integration) use ($indiaMart, &$added) {
                if ($integration->acceptsTraffic() && $indiaMart->isDue($integration)) {
                    $added += $indiaMart->pull($integration);
                }
            });

        $tenant->clear();
        $this->info("Added {$added} IndiaMART leads.");

        return self::SUCCESS;
    }
}
