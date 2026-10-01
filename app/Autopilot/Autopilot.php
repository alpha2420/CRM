<?php

namespace App\Autopilot;

use App\Ai\LeadAssistant;
use App\Enums\StatusType;
use App\Integrations\WhatsAppService;
use App\Jobs\AnalyseLead;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AutomationAlertNotification;
use App\Notifications\LeadsHandedOverNotification;
use App\Services\LeadAssigner;
use App\Support\LocalTime;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The routine steps the CRM takes on its own when something happens to a
 * lead. Each one is a switch on Settings → Autopilot and leaves a line in
 * the lead's history, so people can always see what was done and why.
 */
final class Autopilot
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly LeadAssigner $assigner,
        private readonly LeadAssistant $assistant,
    ) {}

    /**
     * A person sent the first WhatsApp message to a lead still in the
     * first stage, so it has been contacted: move it one stage on.
     */
    public function messageSent(Lead $lead, ?User $sender): void
    {
        $organization = $lead->loadMissing('organization')->organization;
        $first = $organization->defaultStatus();

        if ($sender === null || $first === null || $lead->status_id !== $first->id
            || ! $organization->autopilot()->on('contacted_on_first_message')) {
            return;
        }

        $next = $organization->leadStatuses()
            ->where('type', StatusType::Open)
            ->whereKeyNot($first->id)
            ->ordered()
            ->first();

        if ($next !== null) {
            $this->record($lead->fill(['status_id' => $next->id]), "Moved to {$next->name} after the first WhatsApp message.");
        }
    }

    /**
     * A lead wrote to us on WhatsApp.
     */
    public function messageReceived(Lead $lead): void
    {
        $organization = $lead->loadMissing(['organization', 'status', 'assignee'])->organization;
        $settings = $organization->autopilot();

        if ($settings->on('reopen_returning') && $lead->status?->type === StatusType::Lost) {
            $this->reopen($lead, 'They wrote on WhatsApp again');
        }

        if ($settings->on('away_message') && $this->isAway($settings, $organization)
            && Cache::add("autopilot:away:{$lead->id}", true, now()->addHours(12))) {
            try {
                $this->whatsapp->sendText($lead, null, (string) $settings->get('away_text'));
            } catch (DomainException) {
                // WhatsApp was disconnected meanwhile; nothing to send.
            }
        }

        $stale = $lead->ai_insight_at === null || $lead->ai_insight_at->lt(now()->subHours(6));
        if ($settings->on('ai_on_reply') && $stale && $this->assistant->availableFor($organization)
            && $this->assistant->remainingThisMonth($organization) > 0) {
            AnalyseLead::dispatch($lead->id);
        }
    }

    /**
     * A known lead enquired again through a form, an ad or the API.
     */
    public function repeatEnquiry(Lead $lead, string $sourceName): void
    {
        $lead->loadMissing(['organization', 'status', 'assignee']);

        if (! $lead->organization->autopilot()->on('reopen_returning')) {
            return;
        }

        if ($lead->status?->type === StatusType::Lost) {
            $this->reopen($lead, "They enquired again via {$sourceName}");

            return;
        }

        if ($lead->status?->type === StatusType::Open) {
            if ($lead->next_follow_up_at === null || $lead->next_follow_up_at->isFuture()) {
                $lead->forceFill(['next_follow_up_at' => now()])->save();
            }
            $lead->assignee?->notify(new AutomationAlertNotification($lead, 'Enquired again'));
        }
    }

    /**
     * Someone was deactivated or removed: share their open leads among the
     * remaining agents so none are left without an owner.
     *
     * @return int how many leads were passed on
     */
    public function memberLeft(User $member): int
    {
        $organization = $member->loadMissing('organization')->organization;

        if (! $organization->autopilot()->on('share_leads_of_leavers')) {
            return 0;
        }

        $leads = Lead::query()
            ->where('assigned_to', $member->id)
            ->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Open)->select('id'))
            ->get();
        $received = [];

        DB::transaction(function () use ($leads, $organization, $member, &$received) {
            foreach ($leads as $lead) {
                $to = $this->assigner->nextInRotation($organization, except: $member->id);
                if ($to === null) {
                    return;
                }

                // Saved quietly: one summary per person instead of a
                // notification for every lead.
                $lead->forceFill(['assigned_to' => $to])->saveQuietly();
                $this->note($lead, "Passed on from {$member->name}, who left the team.");
                $received[$to] = ($received[$to] ?? 0) + 1;
            }
        });

        foreach ($received as $userId => $count) {
            User::query()->find($userId)?->notify(new LeadsHandedOverNotification($count, $member->name));
        }

        return array_sum($received);
    }

    /**
     * Outside working hours in the workspace's own time zone?
     */
    public function isAway(AutopilotSettings $settings, Organization $organization): bool
    {
        $now = LocalTime::of(now(), $organization->timezone);

        return ($now->isSunday() && ! $settings->on('work_sundays'))
            || $now->hour < $settings->number('work_start')
            || $now->hour >= $settings->number('work_end');
    }

    /**
     * Bring a lost lead back to the start of the pipeline, due now.
     */
    public function reopen(Lead $lead, string $reason): void
    {
        $first = $lead->loadMissing(['organization', 'assignee'])->organization->defaultStatus();

        if ($first === null) {
            return;
        }

        $lead->fill(['status_id' => $first->id, 'next_follow_up_at' => now()]);
        if (! $lead->assignee?->is_active) {
            $lead->assigned_to = $this->assigner->nextInRotation($lead->organization) ?? $lead->assigned_to;
        }

        $this->record($lead, "Reopened: {$reason}.");
        $lead->unsetRelation('assignee')->load('assignee')->assignee?->notify(new AutomationAlertNotification($lead, 'Back again'));
    }

    /**
     * Save a change to the lead and explain it in the lead's history.
     */
    public function record(Lead $lead, string $note): void
    {
        DB::transaction(function () use ($lead, $note) {
            $lead->save();
            $this->note($lead, $note);
        });
    }

    public function note(Lead $lead, string $note): void
    {
        $activity = $lead->activities()->make(['status_id' => $lead->status_id, 'note' => "Autopilot: {$note}"]);
        $activity->organization_id = $lead->organization_id;
        $activity->save();
    }
}
