<?php

namespace App\Autopilot;

use App\Enums\Role;
use App\Enums\StatusType;
use App\Integrations\WhatsAppService;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\WhatsAppTemplate;
use App\Notifications\AutomationAlertNotification;
use App\Services\LeadAssigner;
use App\Support\WorkingHours;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * The time-based half of Autopilot, run every few minutes by the
 * scheduler: pass on leads nobody answered, nudge leads that went quiet
 * and close leads that are long dead. Nothing runs outside the
 * workspace's working hours, so nobody gets a message at 3 a.m.
 */
final class AutopilotSweep
{
    /** Leads handled per workspace per run, to keep each run short. */
    private const BATCH = 200;

    public function __construct(
        private readonly Autopilot $autopilot,
        private readonly LeadAssigner $assigner,
        private readonly WhatsAppService $whatsapp,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return array{passed_on: int, reengaged: int, closed: int}
     */
    public function run(): array
    {
        $totals = ['passed_on' => 0, 'reengaged' => 0, 'closed' => 0];

        foreach (Organization::query()->whereNull('suspended_at')->cursor() as $organization) {
            $settings = $organization->autopilot();

            if (! $organization->isActive() || $this->autopilot->isAway($organization)) {
                continue;
            }

            $this->tenant->set($organization->id);
            $totals['passed_on'] += $settings->on('speed_to_lead') ? $this->passOnUnanswered($organization, $settings) : 0;
            $totals['reengaged'] += $settings->on('reengage') ? $this->reengageQuiet($organization, $settings) : 0;
            $totals['closed'] += $settings->on('auto_close') ? $this->closeDead($organization, $settings) : 0;
        }

        $this->tenant->clear();

        return $totals;
    }

    /**
     * A lead that arrived on its own (form, ad, WhatsApp, API) and that
     * nobody contacted in time goes to the next agent; admins are told.
     * Overnight leads get the same grace period after opening time.
     */
    private function passOnUnanswered(Organization $organization, AutopilotSettings $settings): int
    {
        $minutes = $settings->number('speed_to_lead_minutes');
        if (now()->lt(WorkingHours::for($organization)->opensToday()->addMinutes($minutes))) {
            return 0;
        }

        $leads = $this->openLeads()
            ->whereNull('created_by')
            ->whereNull('first_contacted_at')
            ->whereNull('escalated_at')
            ->whereNotNull('assigned_to')
            ->whereBetween('created_at', [now()->subDay(), now()->subMinutes($minutes)])
            ->with(['assignee', 'organization'])
            ->limit(self::BATCH)
            ->get();
        $admins = $organization->users()->active()->where('role', Role::Admin)->get();

        foreach ($leads as $lead) {
            $from = $lead->assignee->name ?? 'nobody';
            $to = $this->assigner->nextInRotation($organization, except: $lead->assigned_to);
            $lead->escalated_at = now();

            if ($to !== null) {
                $lead->assigned_to = $to;
                $this->autopilot->record($lead, "Nobody contacted this lead within {$minutes} minutes, so it was passed from {$from} to {$lead->load('assignee')->assignee->name}.");
            } else {
                $this->autopilot->record($lead, "Nobody contacted this lead within {$minutes} minutes.");
            }

            Notification::send($admins->where('id', '!=', $to), new AutomationAlertNotification($lead, "Not contacted in {$minutes} min"));
        }

        return $leads->count();
    }

    /**
     * Once per quiet spell: send the chosen WhatsApp template and make the
     * lead due, so its owner checks in.
     */
    private function reengageQuiet(Organization $organization, AutopilotSettings $settings): int
    {
        $days = $settings->number('reengage_days');
        $template = WhatsAppTemplate::query()->find($settings->get('reengage_template_id'));
        $template = $template?->isApproved() ? $template : null;

        $leads = $this->quietFor($days)
            ->where(fn (Builder $q) => $q->whereNull('reengaged_at')->orWhereColumn('reengaged_at', '<', 'last_activity_at'))
            ->with('organization')
            ->limit(self::BATCH)
            ->get();

        foreach ($leads as $lead) {
            $sent = false;
            if ($template !== null) {
                try {
                    $this->whatsapp->sendTemplate($lead, null, $template, $this->whatsapp->defaultParameters($lead, $template->variables));
                    $sent = true;
                } catch (DomainException) {
                    // WhatsApp not connected: the owner still gets the nudge.
                }
            }

            $lead->forceFill(['next_follow_up_at' => now(), 'reengaged_at' => now()]);
            $this->autopilot->record($lead, "No reply for {$days} days".($sent ? ", so the “{$template->name}” WhatsApp template was sent" : '').'. Time to check in.');
        }

        return $leads->count();
    }

    /**
     * Close leads with no activity and no messages for a long time.
     */
    private function closeDead(Organization $organization, AutopilotSettings $settings): int
    {
        $lost = $organization->leadStatuses()->where('type', StatusType::Lost)->ordered()->first();

        if ($lost === null) {
            return 0;
        }

        $days = $settings->number('auto_close_days');
        $leads = $this->quietFor($days)->limit(self::BATCH)->get();

        foreach ($leads as $lead) {
            // A clean-up, not a sales event: user automations stay out of it.
            $lead->changedByAutomation = true;
            $this->autopilot->record($lead->fill(['status_id' => $lost->id]), "Closed as {$lost->name} after {$days} days with no activity.");
        }

        return $leads->count();
    }

    /**
     * Open leads with no follow-up logged and no WhatsApp message for $days.
     *
     * @return Builder<Lead>
     */
    private function quietFor(int $days): Builder
    {
        $cutoff = now()->subDays($days);

        return $this->openLeads()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->whereNull('last_activity_at')->where('created_at', '<', $cutoff))
                ->orWhere('last_activity_at', '<', $cutoff))
            ->where(fn (Builder $q) => $q->whereNull('last_message_at')->orWhere('last_message_at', '<', $cutoff));
    }

    /** @return Builder<Lead> */
    private function openLeads(): Builder
    {
        return Lead::query()->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Open)->select('id'));
    }
}
