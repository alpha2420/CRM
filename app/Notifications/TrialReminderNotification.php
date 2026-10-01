<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialReminderNotification extends Notification
{
    public function __construct(private readonly Organization $organization, private readonly int $daysLeft) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->organization->name;
        $leads = $this->organization->leads()->count();
        $mail = new MailMessage;

        if ($this->daysLeft <= 0) {
            return $mail
                ->subject("Your {$name} trial has ended")
                ->line('Your free trial of '.config('app.name').' has ended, so your team can no longer open the CRM.')
                ->line("Your {$leads} leads and all settings are safe. Choose a plan to pick up exactly where you left off.")
                ->action('Choose a plan', route('settings.billing'));
        }

        $when = $this->daysLeft === 1 ? 'tomorrow' : "in {$this->daysLeft} days";

        return $mail
            ->subject("Your {$name} trial ends {$when}")
            ->line("Your free trial ends {$when}. So far your workspace holds {$leads} leads.")
            ->line('Choose a plan now and nothing changes for your team — no setup, no data to move.')
            ->action('See plans', route('settings.billing'))
            ->line('Need more time to decide? Reply to this email.');
    }
}
