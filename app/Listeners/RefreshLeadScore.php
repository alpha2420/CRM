<?php

namespace App\Listeners;

use App\Events\FollowUpLogged;
use App\Events\LeadCreated;
use App\Events\LeadUpdated;
use App\Events\RepeatEnquiryReceived;
use App\Events\WhatsAppMessageReceived;
use App\Scoring\ScoreRefresher;

/**
 * Rescores a lead as soon as something that affects its score happens.
 */
class RefreshLeadScore
{
    public function __construct(private readonly ScoreRefresher $refresher) {}

    public function handleCreated(LeadCreated $event): void
    {
        $this->refresher->refresh($event->lead);
    }

    public function handleUpdated(LeadUpdated $event): void
    {
        if (array_intersect($event->changed, ScoreRefresher::INPUTS) !== []) {
            $this->refresher->refresh($event->lead);
        }
    }

    public function handleFollowUp(FollowUpLogged $event): void
    {
        $this->refresher->refresh($event->lead);
    }

    public function handleMessage(WhatsAppMessageReceived $event): void
    {
        $this->refresher->refresh($event->lead);
    }

    public function handleRepeatEnquiry(RepeatEnquiryReceived $event): void
    {
        $this->refresher->refresh($event->lead);
    }
}
