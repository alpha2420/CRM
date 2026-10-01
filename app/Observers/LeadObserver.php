<?php

namespace App\Observers;

use App\Enums\StatusType;
use App\Events\LeadAssigned;
use App\Events\LeadCreated;
use App\Events\LeadStatusChanged;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Tenancy\OrganizationScope;
use Illuminate\Support\Facades\Auth;

/**
 * Turns lead saves into domain events, so notifications and automations
 * react the same way whether a change came from the UI, an import, the
 * API, a webhook or an automation.
 */
class LeadObserver
{
    /**
     * Keep derived timestamps right: a new follow-up date re-arms its
     * reminder, and reaching won/lost records when the lead was closed.
     */
    public function saving(Lead $lead): void
    {
        if ($lead->isDirty('next_follow_up_at')) {
            $lead->reminded_at = null;
        }

        if ($lead->isDirty('status_id')) {
            $type = LeadStatus::withoutGlobalScope(OrganizationScope::class)->whereKey($lead->status_id)->first()?->type;
            $lead->closed_at = $type === StatusType::Open ? null : ($lead->closed_at ?? now());
        }
    }

    public function created(Lead $lead): void
    {
        LeadCreated::dispatch($lead, Auth::user());

        if ($lead->assigned_to !== null) {
            LeadAssigned::dispatch($lead, Auth::user());
        }
    }

    public function updated(Lead $lead): void
    {
        if ($lead->wasChanged('status_id')) {
            LeadStatusChanged::dispatch($lead, $lead->getOriginal('status_id'), Auth::user());
        }

        if ($lead->wasChanged('assigned_to') && $lead->assigned_to !== null) {
            LeadAssigned::dispatch($lead, Auth::user());
        }
    }
}
