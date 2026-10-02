<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Notifications\FollowUpDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_times_are_entered_and_shown_in_the_workspace_time_zone(): void
    {
        $admin = $this->registerOrganization();
        $this->assertSame('Asia/Kolkata', $admin->organization->timezone);
        $lead = Lead::factory()->for($admin->organization)->create();
        $contacted = LeadStatus::where('name', 'Contacted')->first();

        // 11:00 in India is 05:30 UTC.
        $this->actingAs($admin)->post("/leads/{$lead->id}/activities", ['status_id' => $contacted->id, 'next_follow_up_at' => '2030-01-10T11:00']);

        $this->assertSame('2030-01-10 05:30:00', $lead->fresh()->next_follow_up_at->utc()->toDateTimeString());
        $this->get("/leads/{$lead->id}/edit")->assertSee('value="2030-01-10T11:00"', false);
        $this->get("/leads/{$lead->id}")->assertSee('10 Jan 2030, 11:00');
    }

    public function test_reminders_fire_at_the_local_time_not_utc(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2030-01-10 10:59', 'Asia/Kolkata'));
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        Lead::factory()->for($admin->organization)->create([
            'assigned_to' => $agent->id,
            'next_follow_up_at' => Carbon::parse('2030-01-10 11:00', 'Asia/Kolkata')->utc(),
        ]);

        $this->artisan('crm:send-reminders');
        Notification::assertNotSentTo($agent, FollowUpDueNotification::class);

        $this->travelTo(Carbon::parse('2030-01-10 11:01', 'Asia/Kolkata'));
        $this->artisan('crm:send-reminders');
        Notification::assertSentTo($agent, FollowUpDueNotification::class, fn ($n) => str_contains($n->toArray($agent)['body'], '10 Jan, 11:00'));
    }

    public function test_today_means_the_local_day(): void
    {
        // 00:30 on 10 Jan in India is still 9 Jan in UTC.
        $this->travelTo(Carbon::parse('2030-01-10 00:30', 'Asia/Kolkata'));
        $admin = $this->registerOrganization();
        Lead::factory()->for($admin->organization)->create(['name' => 'Early Bird']);
        Lead::factory()->for($admin->organization)->create(['name' => 'Yesterday Lead', 'created_at' => Carbon::parse('2030-01-09 23:00', 'Asia/Kolkata')->utc()]);

        $stats = $this->actingAs($admin)->get('/dashboard')->viewData('stats');

        $this->assertSame(1, $stats['new_today']);
    }

    public function test_admins_change_the_workspace_time_zone_and_sign_up_uses_the_browser_zone(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin)->put('/settings/workspace', ['name' => 'Acme', 'timezone' => 'Asia/Dubai'])->assertSessionHasNoErrors();
        $this->assertSame('Asia/Dubai', $admin->organization->fresh()->timezone);
        $this->put('/settings/workspace', ['name' => 'Acme', 'timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');
        $this->post('/logout');

        $this->post('/register', [
            'organization_name' => 'Gulf Co', 'name' => 'Owner', 'email' => 'owner@gulf.test', 'timezone' => 'Asia/Dubai',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ]);
        $this->assertSame('Asia/Dubai', User::where('email', 'owner@gulf.test')->sole()->organization->timezone);
    }

    public function test_the_browser_zone_never_blocks_sign_up(): void
    {
        // Chrome reports India as "Asia/Calcutta", which PHP lists only as a legacy alias.
        $this->post('/register', [
            'organization_name' => 'Pune Co', 'name' => 'Owner', 'email' => 'owner@pune.test', 'timezone' => 'Asia/Calcutta',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Asia/Kolkata', User::where('email', 'owner@pune.test')->sole()->organization->timezone);
        $this->post('/logout');

        $this->post('/register', [
            'organization_name' => 'Odd Co', 'name' => 'Owner', 'email' => 'owner@odd.test', 'timezone' => 'Mars/Olympus',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertSessionHasNoErrors();
        $this->assertSame(config('crm.default_timezone'), User::where('email', 'owner@odd.test')->sole()->organization->timezone);
    }
}
