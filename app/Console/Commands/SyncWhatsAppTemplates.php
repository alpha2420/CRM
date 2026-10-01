<?php

namespace App\Console\Commands;

use App\Enums\IntegrationType;
use App\Integrations\WhatsAppService;
use App\Models\Integration;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('crm:sync-templates')]
#[Description('Fetch every workspace\'s approved WhatsApp templates from Meta')]
class SyncWhatsAppTemplates extends Command
{
    public function handle(WhatsAppService $whatsapp, TenantContext $tenant): int
    {
        $synced = 0;

        Integration::withoutGlobalScopes()
            ->where('type', IntegrationType::WhatsApp)
            ->where('is_active', true)
            ->with('organization')
            ->each(function (Integration $integration) use ($whatsapp, $tenant, &$synced) {
                $tenant->set($integration->organization_id);

                try {
                    $whatsapp->syncTemplates($integration);
                    $synced++;
                } catch (Throwable $e) {
                    // One workspace's expired token must not stop the others.
                    Log::warning("Template sync failed for integration {$integration->id}: {$e->getMessage()}");
                }
            });

        $tenant->clear();
        $this->info("Synced templates for {$synced} workspaces.");

        return self::SUCCESS;
    }
}
