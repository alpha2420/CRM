<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Admins handle every lead in their organization; agents only the leads
     * assigned to them. The organization check backs up the tenant scope.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $lead->organization_id === $user->organization_id
            && ($user->isAdmin() || $lead->assigned_to === $user->id);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $lead->organization_id === $user->organization_id && $user->isAdmin();
    }
}
