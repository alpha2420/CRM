<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class LeadsHandedOverNotification extends Notification
{
    public function __construct(private readonly int $count, private readonly string $from) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'automation',
            'title' => "{$this->count} ".Str::plural('lead', $this->count)." passed on to you from {$this->from}",
            'body' => 'They left the team. The leads are in your list now.',
            'url' => route('leads.index', ['assigned_to' => $notifiable->getKey()], false),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toWebPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return ['title' => $data['title'], 'body' => $data['body'], 'url' => url($data['url'])];
    }
}
