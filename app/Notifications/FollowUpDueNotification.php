<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Channels\WebPushChannel;
use App\Support\LocalTime;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class FollowUpDueNotification extends Notification
{
    public function __construct(private readonly Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'reminder',
            'title' => "Follow up with {$this->lead->name}",
            'body' => 'Due '.$this->dueAt()->format('d M, H:i').' · '.$this->lead->phone,
            'url' => route('leads.show', $this->lead, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Follow-up due: {$this->lead->name}")
            ->line("It's time to follow up with {$this->lead->name} ({$this->lead->phone}).")
            ->line('Scheduled for '.$this->dueAt()->format('d M Y, H:i').'.')
            ->action('Open lead', route('leads.show', $this->lead));
    }

    private function dueAt(): Carbon
    {
        return LocalTime::of($this->lead->next_follow_up_at, $this->lead->organization->timezone);
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
