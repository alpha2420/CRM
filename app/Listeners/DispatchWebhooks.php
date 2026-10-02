<?php

namespace App\Listeners;

use App\Enums\StatusType;
use App\Enums\WebhookEvent;
use App\Events\LeadAssigned;
use App\Events\LeadCreated;
use App\Events\LeadStatusChanged;
use App\Events\WhatsAppMessageReceived;
use App\Models\LeadStatus;
use App\Webhooks\WebhookDispatcher;

/**
 * Turns CRM events into webhook deliveries for apps that asked for them.
 */
class DispatchWebhooks
{
    public function __construct(private readonly WebhookDispatcher $webhooks) {}

    public function handleCreated(LeadCreated $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::LeadCreated, $event->lead);
    }

    public function handleStatusChanged(LeadStatusChanged $event): void
    {
        $previous = $event->previousStatusId ? LeadStatus::query()->find($event->previousStatusId)?->name : null;
        $this->webhooks->dispatch(WebhookEvent::LeadStatusChanged, $event->lead, ['previous_status' => $previous]);

        $type = LeadStatus::query()->find($event->lead->status_id)?->type;
        match ($type) {
            StatusType::Won => $this->webhooks->dispatch(WebhookEvent::LeadWon, $event->lead),
            StatusType::Lost => $this->webhooks->dispatch(WebhookEvent::LeadLost, $event->lead),
            default => null,
        };
    }

    public function handleAssigned(LeadAssigned $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::LeadAssigned, $event->lead);
    }

    public function handleMessage(WhatsAppMessageReceived $event): void
    {
        $this->webhooks->dispatch(WebhookEvent::WhatsAppReceived, $event->lead, ['message' => $event->message->body]);
    }
}
