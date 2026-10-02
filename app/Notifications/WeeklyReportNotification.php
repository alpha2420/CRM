<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Support\Duration;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyReportNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $kpis  from ReportService
     */
    public function __construct(
        private readonly array $kpis,
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $to,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $k = $this->kpis;

        return (new MailMessage)
            ->subject($this->headline())
            ->greeting('Your week, '.$this->from->format('j M').' – '.$this->to->format('j M'))
            ->line("New leads: {$k['new']} (the week before: {$k['new_previous']})")
            ->line("Contacted: {$k['contacted_rate']}% · {$k['speed']['rate']}% answered within 5 minutes (median first reply ".Duration::seconds($k['speed']['median']).')')
            ->line("Won: {$k['won']} (".Money::full($k['won_value']).") · win rate {$k['win_rate']}%")
            ->action('Open reports', route('reports', ['range' => '7']));
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->headline(),
            'body' => "Contacted {$this->kpis['contacted_rate']}% · won ".Money::short($this->kpis['won_value']),
            'url' => route('reports', ['range' => '7']),
        ];
    }

    private function headline(): string
    {
        return "Last week: {$this->kpis['new']} new leads, {$this->kpis['won']} won";
    }
}
