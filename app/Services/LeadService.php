<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place where leads are created and follow-ups are logged, so
 * the web UI, CSV import and the public API all behave the same way.
 */
final class LeadService
{
    public function __construct(private readonly LeadAssigner $assigner) {}

    /**
     * @param  array<string, mixed>  $data  validated lead attributes
     */
    public function create(Organization $organization, array $data, ?User $actor = null): Lead
    {
        $lead = new Lead($data);
        $lead->organization_id = $organization->id;
        $lead->status_id ??= $organization->defaultStatus()?->id;
        $requested = isset($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        $lead->assigned_to = $this->assigner->assigneeFor($organization, $actor, $requested);
        $lead->created_by = $actor?->id;
        $lead->save();

        return $lead;
    }

    /**
     * Record a follow-up and move the lead to its new status and next date.
     *
     * @param  array{status_id: int, note?: ?string, next_follow_up_at?: ?string}  $data
     */
    public function logActivity(Lead $lead, User $user, array $data): LeadActivity
    {
        return DB::transaction(function () use ($lead, $user, $data) {
            $activity = $lead->activities()->make($data);
            $activity->organization_id = $lead->organization_id;
            $activity->user()->associate($user);
            $activity->save();

            $lead->forceFill([
                'status_id' => $activity->status_id,
                'next_follow_up_at' => $activity->next_follow_up_at,
                'last_activity_at' => $activity->created_at,
                'first_contacted_at' => $lead->first_contacted_at ?? $activity->created_at,
            ])->save();

            return $activity;
        });
    }

    /**
     * Move a lead to another stage (board drag, stage bar, inbox panel).
     * It is recorded in the lead's history like a follow-up, and the next
     * follow-up date stays as it was.
     */
    public function changeStatus(Lead $lead, User $user, int $statusId): void
    {
        if ($lead->status_id === $statusId) {
            return;
        }

        $this->logActivity($lead, $user, [
            'status_id' => $statusId,
            'note' => null,
            'next_follow_up_at' => $lead->next_follow_up_at,
        ]);
    }
}
