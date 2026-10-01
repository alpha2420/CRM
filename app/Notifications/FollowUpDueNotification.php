<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpDueNotification extends Notification
{
    public function __construct(private readonly Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'reminder',
            'title' => "Follow up with {$this->lead->name}",
            'body' => 'Due '.$this->lead->next_follow_up_at->format('d M, H:i').' · '.$this->lead->phone,
            'url' => route('leads.show', $this->lead, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Follow-up due: {$this->lead->name}")
            ->line("It's time to follow up with {$this->lead->name} ({$this->lead->phone}).")
            ->line('Scheduled for '.$this->lead->next_follow_up_at->format('d M Y, H:i').'.')
            ->action('Open lead', route('leads.show', $this->lead));
    }
}
