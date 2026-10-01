<?php

use Illuminate\Support\Facades\Schedule;

/*
| Run `php artisan schedule:run` every minute from cron. That is the only
| background process this app needs.
*/

Schedule::command('crm:send-reminders')->everyFiveMinutes()->withoutOverlapping();

// Works through queued jobs (WhatsApp sends, lead-ad fetches) on hosts
// without a long-running worker. With a real `queue:work` daemon, set
// CRM_SCHEDULER_RUNS_QUEUE=false.
if (config('crm.scheduler_runs_queue')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
        ->everyMinute()
        ->withoutOverlapping();
}

Schedule::command('queue:prune-failed --hours=168')->daily();
