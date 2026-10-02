<?php

namespace App\Services;

use App\Enums\AutomationTrigger;
use App\Enums\Feature;
use App\Enums\Priority;
use App\Integrations\WhatsAppService;
use App\Models\Automation;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Sequence;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Notifications\AutomationAlertNotification;
use App\Sequences\SequenceEnroller;
use App\Tenancy\OrganizationScope;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Runs the workspace's "when / if / then" rules against a lead.
 */
final class AutomationRunner
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly SequenceEnroller $sequences,
    ) {}

    /** A message rule fires at most once per lead in this many hours. */
    private const MESSAGE_RULE_COOLDOWN_HOURS = 24;

    /**
     * @param  array{message?: string}  $context  what happened (e.g. the message text)
     */
    public function run(Lead $lead, AutomationTrigger $trigger, array $context = []): void
    {
        $organization = $lead->loadMissing('organization')->organization;

        if (! $organization->canUse(Feature::Automations)) {
            return;
        }

        $rules = Automation::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            // Conditions are judged on the lead as it was when the event
            // happened, so one rule's changes can't make another rule fire.
            if (! $rule->matches($lead, $context)) {
                continue;
            }

            if ($trigger === AutomationTrigger::WhatsAppReceived && $this->ranSince($rule, $lead, now()->subHours(self::MESSAGE_RULE_COOLDOWN_HOURS))) {
                continue;
            }

            $this->fire($rule, $lead);
        }
    }

    /**
     * Run one rule's actions for a lead and log it (also used by the
     * scheduler for time-based rules).
     */
    public function fire(Automation $rule, Lead $lead): void
    {
        // Actions apply to a fresh copy: earlier rules may have changed it.
        $target = Lead::withoutGlobalScope(OrganizationScope::class)->with('organization')->find($lead->id);

        if ($target === null) {
            return;
        }

        $this->apply($rule, $target);
        $rule->forceFill(['runs' => $rule->runs + 1, 'last_run_at' => now()])->save();

        $rule->runLog()->make()->forceFill(['organization_id' => $rule->organization_id, 'lead_id' => $target->id])->save();
    }

    private function ranSince(Automation $rule, Lead $lead, CarbonInterface $since): bool
    {
        return $rule->runLog()->where('lead_id', $lead->id)->where('created_at', '>=', $since)->exists();
    }

    private function apply(Automation $rule, Lead $lead): void
    {
        $lead->changedByAutomation = true;
        $organizationId = $lead->organization_id;

        if ($userId = $rule->action('assign_to')) {
            $user = User::query()->where('organization_id', $organizationId)->active()->find($userId);
            $lead->assigned_to = $user->id ?? $lead->assigned_to;
        }

        $statusChanged = false;
        if ($statusId = $rule->action('set_status_id')) {
            $status = LeadStatus::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId)->find($statusId);
            $statusChanged = $status !== null && $status->id !== $lead->status_id;
            $lead->status_id = $status->id ?? $lead->status_id;
        }

        if ($priority = Priority::tryFrom((string) $rule->action('set_priority'))) {
            $lead->priority = $priority;
        }

        if ($hours = (int) $rule->action('follow_up_in_hours')) {
            $lead->next_follow_up_at = now()->addHours($hours);
        }

        $lead->save();

        if ($statusChanged) {
            $activity = $lead->activities()->make(['status_id' => $lead->status_id, 'note' => "Status set by automation “{$rule->name}”."]);
            $activity->organization_id = $organizationId;
            $activity->save();
        }

        if ($templateId = $rule->action('whatsapp_template_id')) {
            $this->sendTemplate($rule, $lead, (int) $templateId);
        }

        if ($sequenceId = $rule->action('start_sequence_id')) {
            $sequence = Sequence::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId)->find($sequenceId);
            if ($sequence?->is_active) {
                $this->sequences->enroll($lead, $sequence);
            }
        }

        if ($notifyId = $rule->action('notify_user_id')) {
            User::query()->where('organization_id', $organizationId)->active()->find($notifyId)
                ?->notify(new AutomationAlertNotification($lead, $rule->name));
        }
    }

    private function sendTemplate(Automation $rule, Lead $lead, int $templateId): void
    {
        $template = WhatsAppTemplate::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $lead->organization_id)
            ->find($templateId);

        if ($template === null || ! $template->isApproved()) {
            return;
        }

        try {
            $this->whatsapp->sendTemplate($lead, null, $template, $this->whatsapp->defaultParameters($lead, $template->variables));
        } catch (DomainException $e) {
            Log::info("Automation {$rule->id} skipped WhatsApp: {$e->getMessage()}");
        }
    }
}
