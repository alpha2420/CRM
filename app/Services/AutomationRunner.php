<?php

namespace App\Services;

use App\Enums\AutomationTrigger;
use App\Enums\Feature;
use App\Integrations\WhatsAppService;
use App\Models\Automation;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Notifications\AutomationAlertNotification;
use App\Tenancy\OrganizationScope;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Runs the workspace's "when / if / then" rules against a lead.
 */
final class AutomationRunner
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    public function run(Lead $lead, AutomationTrigger $trigger): void
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
            if (! $rule->matches($lead)) {
                continue;
            }

            // Actions apply to a fresh copy: earlier rules may have changed it.
            $target = Lead::withoutGlobalScope(OrganizationScope::class)->with('organization')->find($lead->id);

            if ($target === null) {
                return;
            }

            $this->apply($rule, $target);
            $rule->forceFill(['runs' => $rule->runs + 1, 'last_run_at' => now()])->save();
        }
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
