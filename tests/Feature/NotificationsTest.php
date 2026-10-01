<?php

namespace Tests\Feature;

use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Notifications\FollowUpDueNotification;
use App\Notifications\LeadAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agents_are_told_when_a_lead_is_assigned_to_them_but_not_by_themselves(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($admin)->post('/leads', ['name' => 'New One', 'phone' => '9876500001', 'priority' => 'medium']);
        Notification::assertSentTo($agent, LeadAssignedNotification::class);

        $this->actingAs($agent)->post('/leads', ['name' => 'Own One', 'phone' => '9876500002', 'priority' => 'medium']);
        Notification::assertSentToTimes($agent, LeadAssignedNotification::class, 1);
    }

    public function test_due_follow_ups_are_reminded_once_per_date(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $due = Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id, 'next_follow_up_at' => now()->subMinutes(5)]);
        Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id, 'next_follow_up_at' => now()->addHour()]);
        $won = LeadStatus::where('type', StatusType::Won)->first();
        Lead::factory()->withStatus($won)->create(['assigned_to' => $agent->id, 'next_follow_up_at' => now()->subMinutes(5)]);

        $this->artisan('crm:send-reminders')->assertSuccessful();
        $this->artisan('crm:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($agent, FollowUpDueNotification::class, 1);
        $this->assertNotNull($due->fresh()->reminded_at);

        $due->fresh()->forceFill(['next_follow_up_at' => now()->subMinute()])->save(); // agent picked a new time
        $this->artisan('crm:send-reminders');
        Notification::assertSentToTimes($agent, FollowUpDueNotification::class, 2);
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $lead = Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id]);
        $notification = $agent->notifications()->sole();

        $this->actingAs($agent)->get('/notifications')->assertOk()->assertSee("New lead: {$lead->name}");
        $this->get("/notifications/{$notification->id}")->assertRedirect("/leads/{$lead->id}");
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
