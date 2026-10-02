<?php

namespace App\Automations;

use App\Enums\AutomationTrigger;
use App\Enums\Feature;
use App\Models\Automation;
use App\Models\Lead;
use App\Models\Organization;
use App\Services\AutomationRunner;
use App\Support\WorkingHours;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Time-based automation rules ("quiet for 7 days", "follow-up 4 hours
 * overdue"), checked every few minutes inside working hours. Each rule
 * fires once per occasion: once per quiet spell, once per follow-up date.
 */
final class ScheduledAutomations
{
    /** Leads handled per rule per run, to keep each run short. */
    private const BATCH = 200;

    public function __construct(
        private readonly AutomationRunner $runner,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return int rules fired
     */
    public function run(): int
    {
        $fired = 0;
        $rules = Automation::query()
            ->where('is_active', true)
            ->whereIn('trigger', array_filter(AutomationTrigger::cases(), fn (AutomationTrigger $t) => $t->isScheduled()))
            ->whereNotNull('trigger_after')
            ->get()
            ->groupBy('organization_id');

        foreach ($rules as $organizationId => $organizationRules) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null || ! $organization->isActive() || ! $organization->canUse(Feature::Automations)
                || ! WorkingHours::for($organization)->isOpen()) {
                continue;
            }

            $this->tenant->set((int) $organizationId);
            foreach ($organizationRules as $rule) {
                foreach ($this->candidates($rule)->limit(self::BATCH)->get() as $lead) {
                    if ($rule->matches($lead)) {
                        $this->runner->fire($rule, $lead);
                        $fired++;
                    }
                }
            }
        }

        $this->tenant->clear();

        return $fired;
    }

    /**
     * Leads the rule applies to that it hasn't fired for on this occasion.
     *
     * @return Builder<Lead>
     */
    private function candidates(Automation $rule): Builder
    {
        $notFiredSince = fn (string $since) => fn (QueryBuilder $runs) => $runs->selectRaw('1')
            ->from('automation_runs')
            ->whereColumn('automation_runs.lead_id', 'leads.id')
            ->where('automation_runs.automation_id', $rule->id)
            ->whereRaw("automation_runs.created_at >= {$since}");

        return match ($rule->trigger) {
            AutomationTrigger::LeadQuiet => Lead::query()->open()
                ->quietSince(now()->subDays($rule->trigger_after))
                ->whereNotExists($notFiredSince('coalesce(leads.last_activity_at, leads.created_at)')),
            AutomationTrigger::FollowUpOverdue => Lead::query()->open()
                ->where('next_follow_up_at', '<=', now()->subHours($rule->trigger_after))
                ->whereNotExists($notFiredSince('leads.next_follow_up_at')),
            default => Lead::query()->whereRaw('1 = 0'),
        };
    }
}
