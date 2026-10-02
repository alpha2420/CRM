<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification
{
    public function __construct(private readonly Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $lead = $this->appointment->lead;

        return [
            'kind' => 'reminder',
            'title' => "{$this->appointment->type->label()} with {$lead->name} in an hour",
            'body' => $this->appointment->when().($this->appointment->location ? " · {$this->appointment->location}" : '').' · '.$lead->phone,
            'url' => route('leads.show', $lead, false),
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
