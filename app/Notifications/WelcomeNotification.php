<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) config('plans.trial_days');

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').' — your workspace is ready')
            ->greeting('Welcome, '.strtok($notifiable->name, ' ').'!')
            ->line("Your {$days}-day free trial of every feature has started. Three things get most teams going in five minutes:")
            ->line('1. **Invite your team** — new leads are shared between agents automatically.')
            ->line('2. **Publish your lead form** — a link or embed for your website, Instagram bio or WhatsApp.')
            ->line('3. **Connect WhatsApp** — chat with leads without leaving the CRM.')
            ->action('Open your dashboard', route('dashboard'))
            ->line('Questions? Just reply to this email.');
    }
}
