<?php

namespace App\Routing;

use App\Models\Lead;
use App\Models\Organization;
use App\Models\RoutingRule;
use Illuminate\Support\Facades\DB;

/**
 * Decides who gets a lead that nobody chose by hand. The first active
 * routing rule that matches picks from its own group, in turn; otherwise
 * every agent takes turns. Either way only people who can take a lead
 * now are picked (see AgentPool).
 */
final class LeadRouter
{
    public function __construct(private readonly AgentPool $pool) {}

    public function route(Organization $organization, ?Lead $lead = null): ?int
    {
        return DB::transaction(function () use ($organization, $lead) {
            // One routing decision at a time per workspace, so turns stay fair.
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->id);

            $rule = $lead === null ? null : RoutingRule::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->orderBy('position')->orderBy('id')
                ->get()
                ->first(fn (RoutingRule $rule) => $rule->matches($lead));

            if ($rule !== null) {
                $pick = RoundRobin::next($this->pool->eligible($organization, among: $rule->agentIds()), $rule->last_assigned_user_id);

                if ($pick !== null) {
                    $rule->forceFill(['last_assigned_user_id' => $pick])->save();

                    return $pick;
                }
            }

            return $this->nextInTurn($organization);
        });
    }

    /**
     * The next agent in the workspace-wide rotation, skipping $except.
     */
    public function nextInTurn(Organization $organization, ?int $except = null): ?int
    {
        $pick = RoundRobin::next($this->pool->eligible($organization, except: $except), $organization->last_assigned_user_id);

        if ($pick !== null) {
            $organization->forceFill(['last_assigned_user_id' => $pick])->save();
        }

        return $pick;
    }
}
