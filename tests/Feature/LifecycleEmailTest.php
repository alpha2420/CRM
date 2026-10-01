<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TrialReminderNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LifecycleEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_owners_get_a_welcome_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'organization_name' => 'Acme', 'name' => 'Owner Person', 'email' => 'owner@acme.test',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ]);

        Notification::assertSentTo(User::where('email', 'owner@acme.test')->sole(), WelcomeNotification::class);
    }

    public function test_trial_reminders_go_to_admins_once_at_three_days_one_day_and_the_end(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $organization = $admin->organization;

        $this->travel(10)->days();
        $this->artisan('crm:trial-reminders');
        Notification::assertNothingSentTo($admin);

        $this->travel(26)->hours(); // under 3 days left
        $this->artisan('crm:trial-reminders');
        $this->artisan('crm:trial-reminders');
        Notification::assertSentToTimes($admin, TrialReminderNotification::class, 1);
        Notification::assertNotSentTo($agent, TrialReminderNotification::class);

        $this->travel(2)->days(); // under 1 day left
        $this->artisan('crm:trial-reminders');
        Notification::assertSentToTimes($admin, TrialReminderNotification::class, 2);

        $this->travel(1)->days(); // ended
        $this->artisan('crm:trial-reminders');
        Notification::assertSentToTimes($admin, TrialReminderNotification::class, 3);
        $this->assertSame(3, $organization->fresh()->trial_reminder_stage);

        $mail = (new TrialReminderNotification($organization->fresh(), 0))->toMail($admin);
        $this->assertSame('Your Acme trial has ended', $mail->subject);
    }

    public function test_paid_workspaces_get_no_trial_emails(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $admin->organization->forceFill(['plan' => 'growth', 'subscription_status' => 'active'])->save();

        $this->travel(13)->days();
        $this->artisan('crm:trial-reminders');

        Notification::assertNotSentTo($admin, TrialReminderNotification::class);
    }
}
