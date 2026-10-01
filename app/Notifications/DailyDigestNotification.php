<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class DailyDigestNotification extends Notification
{
    /**
     * @param  array{name: string, due: int, overdue: int, new: int, leads: list<array{name: string, when: string}>, team: array<string, int>}  $digest
     */
    public function __construct(private readonly array $digest) {}

    public function via(object $notifiable): array
    {
        return ['mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->digest;
        $mail = (new MailMessage)
            ->subject($this->headline())
            ->greeting('Good morning, '.Str::before($d['name'], ' ').'.');

        if ($d['due'] > 0) {
            $mail->line("You have {$d['due']} ".Str::plural('follow-up', $d['due']).' today'.($d['overdue'] ? ", {$d['overdue']} of them overdue." : '.'));
            foreach ($d['leads'] as $lead) {
                $mail->line("• {$lead['name']} · {$lead['when']}");
            }
        } else {
            $mail->line('No follow-ups are due today.');
        }

        if ($d['new'] > 0) {
            $mail->line("{$d['new']} new ".Str::plural('lead', $d['new']).' came in yesterday.');
        }

        if ($d['team'] !== []) {
            $mail->line('Overdue follow-ups in the team: '.collect($d['team'])->map(fn (int $n, string $who) => "{$who} {$n}")->implode(', ').'.');
        }

        return $mail->action('Open today\'s follow-ups', route('leads.index', ['stage' => 'due']));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toWebPush(object $notifiable): array
    {
        $d = $this->digest;

        return [
            'title' => $this->headline(),
            'body' => collect([
                $d['overdue'] ? "{$d['overdue']} overdue" : null,
                $d['new'] ? "{$d['new']} new ".Str::plural('lead', $d['new']).' yesterday' : null,
            ])->filter()->implode(' · ') ?: 'Have a good day.',
            'url' => route('leads.index', ['stage' => 'due']),
        ];
    }

    private function headline(): string
    {
        $due = $this->digest['due'];

        return $due ? "Today: {$due} ".Str::plural('follow-up', $due) : 'Your morning summary';
    }
}
