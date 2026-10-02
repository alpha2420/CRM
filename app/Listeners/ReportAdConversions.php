<?php

namespace App\Listeners;

use App\AdConversions\AdConversions;
use App\AdConversions\ConversionEvent;
use App\Events\LeadCreated;
use App\Events\LeadStatusChanged;

/**
 * Leads from Click-to-WhatsApp ads: tell Meta when they arrive, qualify
 * and buy (see AdConversions).
 */
class ReportAdConversions
{
    public function __construct(private readonly AdConversions $conversions) {}

    public function handleCreated(LeadCreated $event): void
    {
        $this->conversions->report($event->lead, ConversionEvent::LeadSubmitted);
    }

    public function handleStatusChanged(LeadStatusChanged $event): void
    {
        $this->conversions->stageReached($event->lead);
    }
}
