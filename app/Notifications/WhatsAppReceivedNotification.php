<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\WhatsAppMessage;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class WhatsAppReceivedNotification extends Notification
{
    public function __construct(private readonly Lead $lead, private readonly WhatsAppMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'whatsapp',
            'title' => "WhatsApp from {$this->lead->name}",
            'body' => Str::limit($this->message->preview(), 80),
            'url' => route('leads.show', ['lead' => $this->lead, 'tab' => 'whatsapp'], false),
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
