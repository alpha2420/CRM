<?php

namespace App\Webhooks;

use App\Enums\WebhookEvent;
use App\Models\Lead;
use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * The JSON body sent for an event: what happened, in which workspace, and
 * the lead as it is now.
 */
final class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $data  details of the event (e.g. the previous stage)
     * @return array<string, mixed>
     */
    public static function for(WebhookEvent|string $event, Lead $lead, array $data = []): array
    {
        $lead->loadMissing(['organization', 'status', 'source', 'assignee', 'lostReason']);

        return [
            'id' => (string) Str::uuid(),
            'event' => $event instanceof WebhookEvent ? $event->value : $event,
            'occurred_at' => now()->toIso8601String(),
            'workspace' => ['id' => $lead->organization_id, 'name' => $lead->organization->name],
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'company' => $lead->company,
                'city' => $lead->city,
                'status' => $lead->status?->name,
                'source' => $lead->source?->name,
                'owner' => $lead->assignee ? ['name' => $lead->assignee->name, 'email' => $lead->assignee->email] : null,
                'value' => $lead->value !== null ? (float) $lead->value : null,
                'priority' => $lead->priority->value,
                'score' => $lead->score,
                'lost_reason' => $lead->lostReason?->name,
                'custom_fields' => $lead->custom_values ?? (object) [],
                'created_at' => $lead->created_at->toIso8601String(),
                'url' => route('leads.show', $lead),
            ],
            'data' => $data === [] ? (object) [] : $data,
        ];
    }

    /**
     * A test delivery ("Send test" in settings).
     *
     * @return array<string, mixed>
     */
    public static function ping(Organization $organization): array
    {
        return [
            'id' => (string) Str::uuid(),
            'event' => 'ping',
            'occurred_at' => now()->toIso8601String(),
            'workspace' => ['id' => $organization->id, 'name' => $organization->name],
            'lead' => null,
            'data' => ['message' => 'Test delivery: your webhook is connected.'],
        ];
    }
}
