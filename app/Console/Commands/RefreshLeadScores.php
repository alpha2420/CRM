<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Scoring\ScoreRefresher;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:score-leads')]
#[Description('Recalculate every lead score, so older activity counts for less over time')]
class RefreshLeadScores extends Command
{
    public function handle(ScoreRefresher $refresher, TenantContext $tenant): int
    {
        $total = 0;

        foreach (Organization::query()->whereNull('suspended_at')->cursor() as $organization) {
            $tenant->set($organization->id);
            $total += $refresher->refreshWorkspace($organization);
        }

        $tenant->clear();
        $this->info("Scored {$total} leads.");

        return self::SUCCESS;
    }
}
