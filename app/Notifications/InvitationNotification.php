<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(private readonly Invitation $invitation, private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization->name;
        $inviter = $this->invitation->inviter->name ?? 'Your team';

        return (new MailMessage)
            ->subject("{$inviter} invited you to {$organization} on ".config('app.name'))
            ->greeting('You are invited!')
            ->line("{$inviter} has invited you to join **{$organization}** as {$this->invitation->role->label()}.")
            ->action('Accept invitation', $this->url)
            ->line('This link expires in '.Invitation::VALID_DAYS.' days.');
    }
}
