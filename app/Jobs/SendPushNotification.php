<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Push\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes one message to every device of a user, in the background so the
 * request that caused it is never slowed down.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  array{title: string, body: string, url: string}  $message
     */
    public function __construct(public readonly int $userId, public readonly array $message) {}

    public function handle(PushSender $sender): void
    {
        if (! $sender->isConfigured()) {
            return;
        }

        PushSubscription::query()->where('user_id', $this->userId)->get()->each(function (PushSubscription $subscription) use ($sender) {
            if ($sender->send($subscription, $this->message) === PushSender::EXPIRED) {
                $subscription->delete();
            }
        });
    }
}
