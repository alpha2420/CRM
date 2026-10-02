<?php

namespace App\Consent;

use App\Models\Lead;
use App\Models\Organization;
use App\Tenancy\TenantContext;

/**
 * Settings → Privacy: "erase leads closed more than N months ago". Keeps
 * data no longer than needed, as the DPDP Act asks.
 */
final class RetentionSweep
{
    private const BATCH = 500;

    public function __construct(
        private readonly LeadEraser $eraser,
        private readonly TenantContext $tenant,
    ) {}

    /** @return int how many leads were erased */
    public function run(): int
    {
        $erased = 0;

        Organization::query()->whereNotNull('retention_months')->each(function (Organization $organization) use (&$erased) {
            $this->tenant->set($organization->id);
            $months = (int) $organization->retention_months;

            Lead::query()
                ->whereNull('erased_at')
                ->whereNotNull('closed_at')
                ->where('closed_at', '<', now()->subMonths($months))
                ->limit(self::BATCH)
                ->get()
                ->each(function (Lead $lead) use ($months, &$erased) {
                    $this->eraser->erase($lead, "closed more than {$months} months ago");
                    $erased++;
                });
        });

        $this->tenant->clear();

        return $erased;
    }
}
