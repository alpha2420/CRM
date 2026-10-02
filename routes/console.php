<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Run `php artisan schedule:run` every minute from cron. That is the only
| background process this app needs.
*/

Schedule::command('crm:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:trial-reminders')->dailyAt('04:30'); // 10:00 India time

// Autopilot (Settings → Autopilot): pass on unanswered leads, nudge quiet
// ones, close dead ones; 9:00 morning summaries in each workspace's time
// zone (every half hour so +5:30 zones hit 9:00 sharp); nightly template sync.
Schedule::command('crm:autopilot')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:digests')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('crm:sync-templates')->dailyAt('20:30'); // 02:00 India time

// Follow-up sequences: send the steps that are due (inside working hours).
Schedule::command('crm:sequences')->everyFiveMinutes()->withoutOverlapping();

// Time-based automation rules (quiet leads, overdue follow-ups).
Schedule::command('crm:automations')->everyTenMinutes()->withoutOverlapping();

// Lead scores fade with time (an old reply counts for less), so refresh hourly.
Schedule::command('crm:score-leads')->hourly()->withoutOverlapping();

// Works through queued jobs (WhatsApp sends, lead-ad fetches) on hosts
// without a long-running worker. With a real `queue:work` daemon, set
// CRM_SCHEDULER_RUNS_QUEUE=false.
if (config('crm.scheduler_runs_queue')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
        ->everyMinute()
        ->withoutOverlapping();
}

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('model:prune')->daily();

// Encrypted database backups (01:30 UTC = 07:00 India), cleanup and an
// alert if the newest backup is too old or the disk too full.
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run --only-db')->dailyAt('01:30');
Schedule::command('backup:monitor')->dailyAt('03:00');

// Heartbeat for the system health panel.
Schedule::call(fn () => Cache::put('crm:scheduler:heartbeat', now()->timestamp, now()->addDay()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
