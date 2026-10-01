<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Notification;

class AutomationAlertNotification extends Notification
{
    public function __construct(private readonly Lead $lead, private readonly string $automation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
