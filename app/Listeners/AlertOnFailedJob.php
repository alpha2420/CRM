<?php

namespace App\Listeners;

use App\Notifications\JobFailedNotification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the platform owners when a background job gives up, at most once
 * an hour per job type, so a broken integration can't flood the inbox.
 */
class AlertOnFailedJob
{
    public function handle(JobFailed $event): void
    {
        $job = $event->job->resolveName();
        Log::error('Background job failed', ['job' => $job, 'error' => $event->exception->getMessage()]);

        $recipients = config('crm.platform_admins');
        if ($recipients === [] || ! Cache::add('crm:job-failed-alert:'.$job, true, now()->addHour())) {
            return;
        }

        Notification::route('mail', $recipients)->notify(new JobFailedNotification($job, $event->exception->getMessage()));
    }
}
