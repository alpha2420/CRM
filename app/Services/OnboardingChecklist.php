<?php

namespace App\Services;

use App\Enums\Feature;
use App\Enums\IntegrationType;
use App\Models\Automation;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\User;

/**
 * The getting-started steps a new workspace sees on its dashboard until
 * they are all done or the admin dismisses the card.
 */
final class OnboardingChecklist
{
    /**
     * @return list<array{title: string, text: string, url: string, done: bool}>|null
     */
    public function for(User $user): ?array
    {
        $organization = $user->organization;

        if (! $user->isAdmin() || $organization->onboarding_dismissed_at !== null) {
            return null;
        }

        $integrations = Integration::query()->pluck('type')->map(fn ($type) => $type->value);
        $steps = [
            ['title' => 'Add your first lead', 'text' => 'By hand, or import a spreadsheet.', 'url' => route('leads.create'), 'done' => Lead::query()->exists()],
            ['title' => 'Set your working hours', 'text' => 'Autopilot follows up inside them.', 'url' => route('settings.autopilot.edit'), 'done' => $organization->autopilot !== null],
            ['title' => 'Invite your team', 'text' => 'New leads are shared between agents.', 'url' => route('users.create'), 'done' => $organization->users()->count() > 1],
            ['title' => 'Publish your lead form', 'text' => 'A link or embed for your website.', 'url' => route('settings.integrations.edit', IntegrationType::WebForm), 'done' => $integrations->contains(IntegrationType::WebForm->value)],
        ];

        if ($organization->canUse(Feature::WhatsApp)) {
            $steps[] = ['title' => 'Connect WhatsApp', 'text' => 'Chat with leads from the CRM.', 'url' => route('settings.integrations.edit', IntegrationType::WhatsApp), 'done' => $integrations->contains(IntegrationType::WhatsApp->value)];
        }

        if ($organization->canUse(Feature::Automations)) {
            $steps[] = ['title' => 'Create an automation', 'text' => 'Greet and route leads automatically.', 'url' => route('settings.automations.create'), 'done' => Automation::query()->exists()];
        }

        return collect($steps)->every('done') ? null : $steps;
    }
}
