<?php

namespace App\Observers;

use App\Enums\StatusType;
use App\Events\LeadAssigned;
use App\Events\LeadCreated;
use App\Events\LeadStatusChanged;
use App\Events\LeadUpdated;
use App\Media\MediaLibrary;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\AuditLogger;
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

            // A lost reason only applies while the lead is lost.
            if ($type !== StatusType::Lost) {
                $lead->lost_reason_id = null;
            }
        }
    }

    /** Attributes worth an activity-log line when they change. */
    private const AUDITED = [
        'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'company' => 'company', 'city' => 'city',
        'status_id' => 'status', 'lost_reason_id' => 'lost reason', 'source_id' => 'source', 'assigned_to' => 'owner', 'value' => 'value',
        'priority' => 'priority', 'notes' => 'notes', 'custom_values' => 'custom fields',
    ];

    public function created(Lead $lead): void
    {
        app(AuditLogger::class)->log('lead.created', "Added lead {$lead->name}", $lead, $this->actor($lead));

        LeadCreated::dispatch($lead, Auth::user());

        if ($lead->assigned_to !== null) {
            LeadAssigned::dispatch($lead, Auth::user());
        }
    }

    public function deleted(Lead $lead): void
    {
        app(AuditLogger::class)->log('lead.deleted', "Deleted lead {$lead->name} ({$lead->phone})", $lead);
        app(MediaLibrary::class)->forgetLead($lead);
    }

    public function updated(Lead $lead): void
    {
        $changed = array_values(array_intersect_key(self::AUDITED, $lead->getChanges()));
        if ($changed !== []) {
            app(AuditLogger::class)->log('lead.updated', "Updated {$lead->name}: ".implode(', ', $changed), $lead, $this->actor($lead));
        }

        if ($lead->wasChanged('status_id')) {
            LeadStatusChanged::dispatch($lead, $lead->getOriginal('status_id'), Auth::user());
        }

        if ($lead->wasChanged('assigned_to') && $lead->assigned_to !== null) {
            LeadAssigned::dispatch($lead, Auth::user());
        }

        LeadUpdated::dispatch($lead, array_keys($lead->getChanges()));
    }

    /** Changes made by automations are not attributed to the signed-in user. */
    private function actor(Lead $lead): ?User
    {
        return $lead->changedByAutomation ? null : Auth::user();
    }
}
