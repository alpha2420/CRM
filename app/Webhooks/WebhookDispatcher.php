<?php

namespace App\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Lead;
use App\Models\Webhook;

/**
 * Queues a delivery to every active webhook of the lead's workspace that
 * asked for this event.
 */
final class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(WebhookEvent $event, Lead $lead, array $data = []): void
    {
        $webhooks = Webhook::withoutGlobalScopes()
            ->where('organization_id', $lead->organization_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $webhook) => $webhook->wants($event));

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = WebhookPayload::for($event, $lead, $data);
        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch($webhook->id, $payload);
        }
    }
}
