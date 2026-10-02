<?php

namespace App\Ops;

use App\Ai\AiProvider;
use App\Push\PushSender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;

/**
 * Production readiness at a glance, for the platform owner: is the
 * scheduler running, is the queue moving, are backups fresh, and is the
 * configuration safe for production?
 */
final class SystemHealth
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const BAD = 'bad';

    public function __construct(private readonly PushSender $push) {}

    /**
     * @return list<array{label: string, status: string, detail: string}>
     */
    public function checks(): array
    {
        return [
            $this->database(),
            $this->scheduler(),
            $this->queue(),
            $this->failedJobs(),
            $this->backups(),
            $this->debugMode(),
            $this->https(),
            $this->mail(),
            $this->errorTracking(),
            $this->optional('Push notifications', $this->push->isConfigured(), 'Run php artisan crm:vapid-keys and set VAPID_* in .env.'),
            $this->optional('AI assistant', AiProvider::current()->isConfigured(), 'Set '.AiProvider::current()->envKey().' to turn on lead analysis.'),
        ];
    }

    public function healthy(): bool
    {
        return collect($this->checks())->doesntContain('status', self::BAD);
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->check('Database', self::OK, config('database.default').' connected');
        } catch (Throwable $e) {
            return $this->check('Database', self::BAD, 'Cannot connect: '.$e->getMessage());
        }
    }

    private function scheduler(): array
    {
        $beat = Cache::get('crm:scheduler:heartbeat');

        if ($beat === null) {
            return $this->check('Scheduler', self::BAD, 'Has not run. Add the cron entry: * * * * * php artisan schedule:run');
        }

        $age = now()->timestamp - (int) $beat;

        return $age > 300
            ? $this->check('Scheduler', self::BAD, 'Last ran '.Carbon::createFromTimestamp($beat)->diffForHumans().'. Reminders and backups are not running.')
            : $this->check('Scheduler', self::OK, 'Last ran '.Carbon::createFromTimestamp($beat)->diffForHumans());
    }

    private function queue(): array
    {
        if (config('queue.default') === 'sync') {
            return $this->check('Background jobs', self::WARN, 'Queue is "sync": WhatsApp sends slow down requests. Use database or redis.');
        }

        if (config('queue.default') !== 'database') {
            return $this->check('Background jobs', self::OK, 'Using '.config('queue.default'));
        }

        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('available_at');
        $waiting = $oldest ? now()->timestamp - (int) $oldest : 0;

        return $waiting > 600
            ? $this->check('Background jobs', self::BAD, "{$pending} waiting, oldest for ".intdiv($waiting, 60).' min. Is the queue worker running?')
            : $this->check('Background jobs', self::OK, $pending ? "{$pending} waiting" : 'Queue is empty');
    }

    private function failedJobs(): array
    {
        $recent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

        return $recent > 0
            ? $this->check('Failed jobs', self::WARN, "{$recent} in the last 24 hours. See php artisan queue:failed.")
            : $this->check('Failed jobs', self::OK, 'None in the last 24 hours');
    }

    private function backups(): array
    {
        try {
            $newest = collect(config('backup.backup.destination.disks'))
                ->map(fn (string $disk) => BackupDestination::create($disk, config('backup.backup.name'))->newestBackup()?->date())
                ->filter()
                ->max();
        } catch (Throwable $e) {
            return $this->check('Backups', self::BAD, 'Backup storage error: '.$e->getMessage());
        }

        if ($newest === null) {
            return $this->check('Backups', self::BAD, 'No backup yet. Runs daily; or run php artisan backup:run --only-db');
        }

        $disks = implode(', ', config('backup.backup.destination.disks'));
        $encrypted = filled(config('backup.backup.password')) ? 'encrypted' : 'NOT encrypted (set BACKUP_ARCHIVE_PASSWORD)';
        $offsite = collect(config('backup.backup.destination.disks'))->contains(fn ($d) => $d !== 'backups' && $d !== 'local');
        $status = $newest->lt(now()->subDay()->subHours(2)) ? self::BAD : (($offsite && filled(config('backup.backup.password'))) ? self::OK : self::WARN);

        return $this->check('Backups', $status, 'Newest '.$newest->diffForHumans()." on {$disks}, {$encrypted}".($offsite ? '' : '. Add an off-site disk (BACKUP_DISKS=backups,s3).'));
    }

    private function debugMode(): array
    {
        return config('app.debug') && app()->isProduction()
            ? $this->check('Debug mode', self::BAD, 'APP_DEBUG is on in production: error pages would expose secrets.')
            : $this->check('Debug mode', self::OK, config('app.debug') ? 'On (fine for local development)' : 'Off');
    }

    private function https(): array
    {
        $https = str_starts_with((string) config('app.url'), 'https://');

        return $https || ! app()->isProduction()
            ? $this->check('HTTPS', $https ? self::OK : self::WARN, $https ? 'APP_URL uses https' : 'APP_URL is not https (fine locally)')
            : $this->check('HTTPS', self::BAD, 'APP_URL is not https in production.');
    }

    private function mail(): array
    {
        return in_array(config('mail.default'), ['log', 'array'], true)
            ? $this->check('Email', self::WARN, 'MAIL_MAILER='.config('mail.default').': emails are not delivered. Configure SMTP.')
            : $this->check('Email', self::OK, 'Sending with '.config('mail.default'));
    }

    private function errorTracking(): array
    {
        return filled(config('sentry.dsn'))
            ? $this->check('Error tracking', self::OK, 'Sentry connected')
            : $this->check('Error tracking', self::WARN, 'Not connected. Set SENTRY_LARAVEL_DSN to be alerted about errors.');
    }

    private function optional(string $label, bool $on, string $hint): array
    {
        return $this->check($label, $on ? self::OK : self::WARN, $on ? 'Configured' : $hint);
    }

    private function check(string $label, string $status, string $detail): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }
}
