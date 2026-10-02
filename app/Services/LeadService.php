<?php

namespace App\Services;

use App\Enums\StatusType;
use App\Events\FollowUpLogged;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\WorkingHours;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
     * @param  bool  $planFirstCall  Autopilot may plan the first follow-up (not for bulk imports)
     */
    public function create(Organization $organization, array $data, ?User $actor = null, bool $planFirstCall = true): Lead
    {
        $lead = new Lead($data);
        $lead->organization_id = $organization->id;
        $lead->status_id ??= $organization->defaultStatus()?->id;

        $autopilot = $organization->autopilot();
        if ($planFirstCall && $lead->next_follow_up_at === null && $autopilot->on('first_follow_up')) {
            $lead->next_follow_up_at = WorkingHours::for($organization)->after(now(), $autopilot->number('first_follow_up_minutes'));
        }

        $requested = isset($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        $lead->assigned_to = $this->assigner->assigneeFor($organization, $actor, $requested, $lead);
        $lead->created_by = $actor?->id;
        $lead->save();

        return $lead;
    }

    /**
     * Record a follow-up and move the lead to its new status and next date.
     *
     * @param  array{status_id: int, note?: ?string, next_follow_up_at?: mixed, lost_reason_id?: ?int, call_outcome?: string, call_seconds?: ?int}  $data
     */
    public function logActivity(Lead $lead, User $user, array $data): LeadActivity
    {
        $data['next_follow_up_at'] ??= $this->nextStepAfter($lead, (int) $data['status_id']);
        $lostReasonId = Arr::pull($data, 'lost_reason_id');

        return DB::transaction(function () use ($lead, $user, $data, $lostReasonId) {
            $activity = $lead->activities()->make($data);
            $activity->organization_id = $lead->organization_id;
            $activity->user()->associate($user);
            $activity->save();

            $lead->recordFirstContact($activity->created_at);
            $lead->forceFill([
                'status_id' => $activity->status_id,
                'next_follow_up_at' => $activity->next_follow_up_at,
                'last_activity_at' => $activity->created_at,
                // Kept only if the new status is a lost one (see LeadObserver).
                'lost_reason_id' => $lostReasonId ?? $lead->lost_reason_id,
            ])->save();

            FollowUpLogged::dispatch($lead, $activity);

            return $activity;
        });
    }

    /**
     * Move a lead to another stage (board drag, stage bar, inbox panel).
     * It is recorded in the lead's history like a follow-up, and the next
     * follow-up date stays as it was.
     */
    public function changeStatus(Lead $lead, User $user, int $statusId, ?int $lostReasonId = null): void
    {
        if ($lead->status_id === $statusId) {
            return;
        }

        $this->logActivity($lead, $user, [
            'status_id' => $statusId,
            'note' => null,
            'next_follow_up_at' => $lead->next_follow_up_at,
            'lost_reason_id' => $lostReasonId,
        ]);
    }

    /**
     * Autopilot: a follow-up logged without a date on a lead that is still
     * open gets the next one planned, so no lead silently drops out.
     */
    private function nextStepAfter(Lead $lead, int $statusId): ?Carbon
    {
        $autopilot = $lead->organization->autopilot();
        $stillOpen = LeadStatus::query()->find($statusId)?->type === StatusType::Open;

        if (! $stillOpen || ! $autopilot->on('next_follow_up')) {
            return null;
        }

        return Carbon::instance(LocalTime::now()->addDays($autopilot->number('next_follow_up_days'))->setTime(11, 0))->utc();
    }
}
