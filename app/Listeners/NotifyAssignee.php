<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Notifications\LeadAssignedNotification;

/**
 * Speed to lead: tell an agent the moment a lead lands in their queue,
 * unless they assigned it to themselves.
 */
class NotifyAssignee
{
    public function handle(LeadAssigned $event): void
    {
        $assignee = $event->lead->loadMissing(['assignee', 'source'])->assignee;

        if ($assignee === null || ! $assignee->is_active || $assignee->is($event->actor)) {
            return;
        }

        $assignee->notify(new LeadAssignedNotification($event->lead));
    }
}
