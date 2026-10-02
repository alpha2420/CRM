<?php

namespace App\Routing;

use App\Enums\Role;
use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;

/**
 * Who can take a new lead right now: active people who aren't away and
 * are under the workspace's open-lead limit. If that leaves nobody, the
 * limit and then "away" are relaxed, so a lead is never left ownerless.
 */
final class AgentPool
{
    /**
     * @param  list<int>|null  $among  limit to these people (a routing rule's group); null means every agent
     * @return list<int>
     */
    public function eligible(Organization $organization, ?array $among = null, ?int $except = null): array
    {
        $people = $organization->users()
            ->active()
            ->when($among === null, fn ($q) => $q->where('role', Role::Agent))
            ->when($among !== null, fn ($q) => $q->whereKey($among))
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except))
            ->orderBy('id')
            ->get(['id', 'is_available']);

        $available = $people->where('is_available', true)->pluck('id')->all();
        $underLimit = $this->underLimit($organization, $available);

        return match (true) {
            $underLimit !== [] => $underLimit,
            $available !== [] => $available,
            default => $people->pluck('id')->all(),
        };
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function underLimit(Organization $organization, array $ids): array
    {
        $limit = $organization->max_open_leads;

        if ($limit === null || $ids === []) {
            return $ids;
        }

        $open = Lead::query()
            ->whereIn('assigned_to', $ids)
            ->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Open)->select('id'))
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        return array_values(array_filter($ids, fn (int $id) => ($open[$id] ?? 0) < $limit));
    }
}
