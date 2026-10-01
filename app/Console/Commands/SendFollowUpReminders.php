<?php

namespace App\Console\Commands;

use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Notifications\FollowUpDueNotification;
use App\Tenancy\OrganizationScope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('crm:send-reminders')]
#[Description('Notify agents about follow-ups that are now due')]
class SendFollowUpReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;
        $openStatusIds = LeadStatus::withoutGlobalScope(OrganizationScope::class)
            ->where('type', StatusType::Open)
            ->select('id');

        Lead::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('assigned_to')
            ->whereIn('status_id', $openStatusIds)
            // Due now, but skip an old backlog so nobody gets a flood.
            ->whereBetween('next_follow_up_at', [now()->subDay(), now()])
            ->whereNull('reminded_at')
            ->with(['assignee', 'organization'])
            ->chunkById(200, function (Collection $leads) use (&$sent) {
                foreach ($leads as $lead) {
                    if ($lead->organization->isActive() && $lead->assignee?->is_active) {
                        $lead->assignee->notify(new FollowUpDueNotification($lead));
                        $sent++;
                    }

                    $lead->forceFill(['reminded_at' => now()])->saveQuietly();
                }
            });

        $this->info("Sent {$sent} reminders.");

        return self::SUCCESS;
    }
}
