<?php

namespace App\Jobs;

use App\Ai\AssistantException;
use App\Ai\LeadAssistant;
use App\Autopilot\Autopilot;
use App\Enums\Priority;
use App\Models\Lead;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Autopilot: refresh a lead's AI summary after they write to us, and flag
 * a hot lead as high priority so it is called first.
 */
class AnalyseLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $leadId) {}

    public function handle(LeadAssistant $assistant, Autopilot $autopilot, TenantContext $tenant): void
    {
        $lead = Lead::withoutGlobalScopes()->with('organization')->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $tenant->set($lead->organization_id);

        try {
            $insight = $assistant->analyse($lead);
        } catch (AssistantException) {
            return; // plan, quota or AI service: nothing to do until next time
        }

        if (($insight['temperature'] ?? null) === 'hot' && $lead->priority !== Priority::High) {
            $autopilot->record($lead->fill(['priority' => Priority::High]), 'AI rated this lead hot, so it is now high priority.');
        }
    }
}
