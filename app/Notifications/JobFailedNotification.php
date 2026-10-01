<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class JobFailedNotification extends Notification
{
    public function __construct(private readonly string $job, private readonly string $error) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(config('app.name').': background job failed ('.class_basename($this->job).')')
            ->line("A background job failed after all retries: **{$this->job}**.")
            ->line('Error: '.Str::limit($this->error, 300))
            ->line('Inspect with `php artisan queue:failed` and retry with `php artisan queue:retry all`. Further failures of this job are batched for an hour.');
    }
}
