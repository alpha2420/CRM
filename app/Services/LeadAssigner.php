<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LeadAssigner
{
    /**
     * Decide who owns a new lead:
     *  1. an agent always owns the leads they create;
     *  2. an admin's explicit choice is kept;
     *  3. otherwise the next active agent, round-robin.
     */
    public function assigneeFor(Organization $organization, ?User $actor, ?int $requestedUserId): ?int
    {
        if ($actor !== null && ! $actor->isAdmin()) {
            return $actor->id;
        }

        return $requestedUserId ?: $this->nextInRotation($organization);
    }

    /**
     * The next active agent in the rotation, skipping $except (used when
     * a lead is passed on from someone).
     */
    public function nextInRotation(Organization $organization, ?int $except = null): ?int
    {
        return DB::transaction(function () use ($organization, $except) {
            // Lock the organization row so concurrent leads don't pick the same agent.
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->id);

            $agentIds = $organization->users()
                ->active()
                ->where('role', Role::Agent)
                ->when($except, fn ($q) => $q->whereKeyNot($except))
                ->orderBy('id')
                ->pluck('id');

            if ($agentIds->isEmpty()) {
                return null;
            }

            $last = (int) $organization->last_assigned_user_id;
            $next = $agentIds->first(fn (int $id) => $id > $last) ?? $agentIds->first();

            $organization->forceFill(['last_assigned_user_id' => $next])->save();

            return $next;
        });
    }
}
