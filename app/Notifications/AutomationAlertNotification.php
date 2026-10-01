<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;

class AutomationAlertNotification extends Notification
{
    public function __construct(private readonly Lead $lead, private readonly string $automation) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'automation',
            'title' => "{$this->automation}: {$this->lead->name}",
            'body' => $this->lead->phone,
            'url' => route('leads.show', $this->lead, false),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toWebPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return ['title' => $data['title'], 'body' => (string) $data['body'], 'url' => url($data['url'])];
    }
}
