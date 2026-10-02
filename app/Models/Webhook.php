<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * An address another app gave us; we POST the chosen events to it,
 * signed with its secret.
 */
#[Fillable(['url', 'events', 'is_active'])]
#[Hidden(['secret'])]
class Webhook extends Model
{
    use BelongsToOrganization;

    /** Consecutive failed deliveries before the webhook is switched off. */
    public const MAX_FAILURES = 50;

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_status' => 'integer',
            'last_delivered_at' => 'datetime',
            'failures' => 'integer',
        ];
    }

    public function wants(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events ?? [], true);
    }

    public function host(): string
    {
        return (string) parse_url($this->url, PHP_URL_HOST);
    }
}
