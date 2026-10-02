<?php

namespace App\Push;

use App\Models\PushSubscription;
use GuzzleHttp\Client as HttpClient;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class WebPushSender implements PushSender
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function send(PushSubscription $subscription, array $message): string
    {
        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]], ['TTL' => 3600], client: new HttpClient(['timeout' => 10]));

        $report = $webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]),
            json_encode($message),
        );

        return match (true) {
            $report->isSuccess() => self::DELIVERED,
            $report->isSubscriptionExpired() => self::EXPIRED,
            default => self::FAILED,
        };
    }
}
