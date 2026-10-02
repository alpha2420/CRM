<?php

namespace Tests\Feature;

use App\Notifications\JobFailedNotification;
use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_sees_system_health_and_the_command_reports_problems(): void
    {
        $owner = $this->registerOrganization();
        config(['crm.platform_admins' => [strtolower($owner->email)]]);

        $this->actingAs($owner)->get('/platform')->assertOk()->assertSee('System health')->assertSee('Scheduler');

        $this->artisan('crm:health')->expectsOutputToContain('Has not run')->assertFailed();

        Cache::put('crm:scheduler:heartbeat', now()->timestamp);
        $this->artisan('crm:health')->expectsOutputToContain('Last ran');
    }

    public function test_owners_are_emailed_once_an_hour_when_a_job_type_keeps_failing(): void
    {
        Notification::fake();
        config(['crm.platform_admins' => ['owner@example.com']]);
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\Jobs\SendWhatsAppMessage');

        event(new JobFailed('database', $job, new Exception('Graph API timeout')));
        event(new JobFailed('database', $job, new Exception('Graph API timeout')));

        Notification::assertSentTimes(JobFailedNotification::class, 1);
        Notification::assertSentTo(new AnonymousNotifiable, JobFailedNotification::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === ['owner@example.com']);
    }

    public function test_the_uptime_endpoint_checks_the_database(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_in_production_the_uptime_endpoint_also_alerts_when_the_scheduler_or_queue_stops(): void
    {
        $this->app['env'] = 'production';
        config(['queue.default' => 'database']);

        $this->get('/up')->assertServerError(); // the scheduler has never run

        Cache::put('crm:scheduler:heartbeat', now()->timestamp);
        $this->get('/up')->assertOk();

        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(20)->timestamp, 'created_at' => now()->subMinutes(20)->timestamp]);
        $this->get('/up')->assertServerError(); // a job has waited 20 minutes: no worker is running

        DB::table('jobs')->delete();
        Cache::put('crm:scheduler:heartbeat', now()->subMinutes(10)->timestamp);
        $this->get('/up')->assertServerError(); // the scheduler stopped 10 minutes ago
    }

    public function test_health_shows_disk_space_and_whether_files_can_be_written(): void
    {
        Cache::put('crm:scheduler:heartbeat', now()->timestamp);

        $this->artisan('crm:health')
            ->expectsOutputToContain('GB free') // the "Disk space" line
            ->expectsOutputToContain('File storage');
    }

    public function test_backups_are_scheduled_daily_with_cleanup_and_monitoring(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('backup:run --only-db')
            ->expectsOutputToContain('backup:clean')
            ->expectsOutputToContain('backup:monitor')
            ->expectsOutputToContain('crm:trial-reminders');
    }
}
