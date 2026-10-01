<?php

namespace App\Notifications\Channels;

use App\Jobs\SendPushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Notification channel: notifications with toWebPush() also reach the
 * user's phone or desktop, if they turned push on.
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toWebPush')) {
            return;
        }

        if (! PushSubscription::query()->where('user_id', $notifiable->id)->exists()) {
            return;
        }

        SendPushNotification::dispatch($notifiable->id, $notification->toWebPush($notifiable))->afterCommit();
    }
}
