<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

#[Fillable(['provider', 'event_id'])]
class WebhookEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * Record a delivery as handled. Returns false if it was handled before.
     * Call inside the same transaction as the processing, so a failure
     * releases the claim and the provider's retry is processed again.
     */
    public static function claim(string $provider, string $eventId): bool
    {
        try {
            static::create(['provider' => $provider, 'event_id' => $eventId]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
