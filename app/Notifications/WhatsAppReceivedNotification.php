<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\WhatsAppMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class WhatsAppReceivedNotification extends Notification
{
    public function __construct(private readonly Lead $lead, private readonly WhatsAppMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'whatsapp',
            'title' => "WhatsApp from {$this->lead->name}",
            'body' => Str::limit((string) $this->message->body, 80),
            'url' => route('leads.show', ['lead' => $this->lead, 'tab' => 'whatsapp'], false),
        ];
    }
}
