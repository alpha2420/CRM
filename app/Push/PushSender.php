<?php

namespace App\Push;

use App\Models\PushSubscription;

/**
 * Delivers one push message to one device. Production uses Web Push
 * (VAPID); tests use a fake.
 */
interface PushSender
{
    public const DELIVERED = 'delivered';

    public const EXPIRED = 'expired';   // the device unsubscribed: forget it

    public const FAILED = 'failed';     // temporary problem

    public function isConfigured(): bool;

    /**
     * @param  array{title: string, body: string, url: string}  $message
     * @return self::DELIVERED|self::EXPIRED|self::FAILED
     */
    public function send(PushSubscription $subscription, array $message): string;
}
