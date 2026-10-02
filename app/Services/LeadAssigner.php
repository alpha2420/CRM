<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use App\Routing\LeadRouter;
use Illuminate\Support\Facades\DB;

final class LeadAssigner
{
    public function __construct(private readonly LeadRouter $router) {}

    /**
     * Decide who owns a new lead:
     *  1. an agent always owns the leads they create;
     *  2. an admin's explicit choice is kept;
     *  3. otherwise the routing rules, then round-robin (see LeadRouter).
     */
    public function assigneeFor(Organization $organization, ?User $actor, ?int $requestedUserId, ?Lead $lead = null): ?int
    {
        if ($actor !== null && ! $actor->isAdmin()) {
            return $actor->id;
        }

        return $requestedUserId ?: $this->router->route($organization, $lead);
    }

    /**
     * The next agent who can take a lead, skipping $except (used when a
     * lead is passed on from someone).
     */
    public function nextInRotation(Organization $organization, ?int $except = null): ?int
    {
        return DB::transaction(fn () => $this->router->nextInTurn(
            Organization::query()->lockForUpdate()->findOrFail($organization->id),
            $except,
        ));
    }
}
