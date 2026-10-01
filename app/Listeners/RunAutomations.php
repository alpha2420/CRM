<?php

namespace App\Listeners;

use App\Enums\AutomationTrigger;
use App\Events\LeadCreated;
use App\Events\LeadStatusChanged;
use App\Services\AutomationRunner;

class RunAutomations
{
    public function __construct(private readonly AutomationRunner $runner) {}

    public function handleLeadCreated(LeadCreated $event): void
    {
        if (! $event->lead->changedByAutomation) {
            $this->runner->run($event->lead, AutomationTrigger::LeadCreated);
        }
    }

    public function handleStatusChanged(LeadStatusChanged $event): void
    {
        if (! $event->lead->changedByAutomation) {
            $this->runner->run($event->lead, AutomationTrigger::StatusChanged);
        }
    }
}
