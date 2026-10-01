<?php

namespace App\Console\Commands;

use App\Billing\PlanCatalog;
use App\Enums\Role;
use App\Models\Organization;
use App\Notifications\TrialReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:trial-reminders')]
#[Description('Email workspace admins before and when their free trial ends')]
class SendTrialReminders extends Command
{
    /** stage => [days before the end it is due, days left to mention] */
    private const STAGES = [1 => 3, 2 => 1, 3 => 0];

    public function handle(): int
    {
        $sent = 0;

        Organization::query()
            ->where('plan', PlanCatalog::TRIAL)
            ->whereNull('suspended_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_reminder_stage', '<', 3)
            // Skip trials that ended long ago: no surprise emails.
            ->where('trial_ends_at', '>', now()->subDays(7))
            ->where('trial_ends_at', '<', now()->addDays(3)->addHour())
            ->each(function (Organization $organization) use (&$sent) {
                $stage = $this->dueStage($organization);

                if ($stage <= $organization->trial_reminder_stage) {
                    return;
                }

                $daysLeft = self::STAGES[$stage];
                $organization->users()->active()->where('role', Role::Admin)->get()
                    ->each(fn ($admin) => $admin->notify(new TrialReminderNotification($organization, $daysLeft)));

                $organization->forceFill(['trial_reminder_stage' => $stage])->save();
                $sent++;
            });

        $this->info("Sent trial emails to {$sent} workspaces.");

        return self::SUCCESS;
    }

    /**
     * The latest reminder whose moment has come: ended, 1 day or 3 days left.
     */
    private function dueStage(Organization $organization): int
    {
        $hoursLeft = now()->diffInHours($organization->trial_ends_at, false);

        return match (true) {
            $hoursLeft <= 0 => 3,
            $hoursLeft <= 24 => 2,
            default => 1,
        };
    }
}
