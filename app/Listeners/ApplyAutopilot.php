<?php

namespace App\Listeners;

use App\Autopilot\Autopilot;
use App\Events\RepeatEnquiryReceived;
use App\Events\WhatsAppMessageReceived;
use App\Events\WhatsAppMessageSent;

class ApplyAutopilot
{
    public function __construct(private readonly Autopilot $autopilot) {}

    public function handleMessageSent(WhatsAppMessageSent $event): void
    {
        $this->autopilot->messageSent($event->lead, $event->sender);
    }

    public function handleMessageReceived(WhatsAppMessageReceived $event): void
    {
        $this->autopilot->messageReceived($event->lead);
    }

    public function handleRepeatEnquiry(RepeatEnquiryReceived $event): void
    {
        $this->autopilot->repeatEnquiry($event->lead, $event->sourceName);
    }
}
