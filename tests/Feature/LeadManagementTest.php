<?php

namespace Tests\Feature;

use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_lead_in_the_default_status_assigned_round_robin(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($admin)
            ->post('/leads', ['name' => 'Jane Doe', 'phone' => '+91 98765-43210', 'priority' => 'high'])
            ->assertRedirect();

        $lead = Lead::sole();
        $this->assertSame('+919876543210', $lead->phone);
        $this->assertSame('New', $lead->status->name);
        $this->assertSame($agent->id, $lead->assigned_to);
        $this->assertSame($admin->id, $lead->created_by);
    }

    public function test_duplicate_phone_numbers_are_rejected(): void
    {
        $admin = $this->registerOrganization();
        Lead::factory()->for($admin->organization)->create(['phone' => '+919876543210']);

        $this->actingAs($admin)
            ->post('/leads', ['name' => 'Again', 'phone' => '+91 98765 43210', 'priority' => 'medium'])
            ->assertSessionHasErrors('phone');
    }

    public function test_agents_own_the_leads_they_create_and_cannot_reassign_them(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($agent)
            ->post('/leads', ['name' => 'Mine', 'phone' => '9876543210', 'priority' => 'medium', 'assigned_to' => $admin->id])
            ->assertRedirect();

        $lead = Lead::sole();
        $this->assertSame($agent->id, $lead->assigned_to);

        $this->put("/leads/{$lead->id}", [
            'name' => 'Mine', 'phone' => '9876543210', 'priority' => 'medium',
            'status_id' => $lead->status_id, 'assigned_to' => $admin->id,
        ])->assertRedirect();

        $this->assertSame($agent->id, $lead->fresh()->assigned_to);
    }

    public function test_agents_cannot_see_or_delete_other_peoples_leads(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $other = $this->addAgent($admin->organization);
        $mine = Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id, 'name' => 'My Lead']);
        $theirs = Lead::factory()->for($admin->organization)->create(['assigned_to' => $other->id, 'name' => 'Their Lead']);

        $this->actingAs($agent)->get('/leads')->assertSee('My Lead')->assertDontSee('Their Lead');
        $this->get("/leads/{$theirs->id}")->assertForbidden();
        $this->delete("/leads/{$mine->id}")->assertForbidden();
        $this->assertNotNull($mine->fresh());
    }

    public function test_admin_updates_and_deletes_a_lead(): void
    {
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();
        $won = LeadStatus::where('type', StatusType::Won)->first();

        $this->actingAs($admin)->put("/leads/{$lead->id}", [
            'name' => 'Renamed', 'phone' => $lead->phone, 'priority' => 'low', 'status_id' => $won->id, 'value' => '1500.50',
        ])->assertRedirect("/leads/{$lead->id}");

        $lead->refresh();
        $this->assertSame('Renamed', $lead->name);
        $this->assertSame($won->id, $lead->status_id);
        $this->assertSame('1500.50', $lead->value);

        $this->delete("/leads/{$lead->id}")->assertRedirect('/leads');
        $this->assertNull($lead->fresh());
    }

    public function test_follow_up_moves_the_lead_and_is_kept_in_history(): void
    {
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();
        $contacted = LeadStatus::where('name', 'Contacted')->first();

        $this->actingAs($admin)->post("/leads/{$lead->id}/activities", [
            'status_id' => $contacted->id,
            'note' => 'Called, call back Friday',
            'next_follow_up_at' => '2030-01-10 11:00:00',
        ])->assertRedirect("/leads/{$lead->id}");

        $lead->refresh();
        $this->assertSame($contacted->id, $lead->status_id);
        $this->assertSame('2030-01-10 05:30:00', $lead->next_follow_up_at->utc()->toDateTimeString(), '11:00 India time is 05:30 UTC.');
        $this->assertNotNull($lead->last_activity_at);

        $this->get("/leads/{$lead->id}")->assertOk()->assertSee('Called, call back Friday');
    }

    public function test_stage_views_split_leads_by_activity_and_outcome(): void
    {
        $admin = $this->registerOrganization();
        $organization = $admin->organization;
        $won = LeadStatus::where('type', StatusType::Won)->first();
        $lost = LeadStatus::where('type', StatusType::Lost)->first();

        Lead::factory()->for($organization)->create(['name' => 'Fresh One']);
        Lead::factory()->for($organization)->create(['name' => 'Working One'])->forceFill(['last_activity_at' => now()->subDay()])->save();
        Lead::factory()->for($organization)->create(['name' => 'Sleepy One'])->forceFill(['last_activity_at' => now()->subDays(60)])->save();
        Lead::factory()->for($organization)->create(['name' => 'Due One', 'next_follow_up_at' => now()->subHour()]);
        Lead::factory()->withStatus($won)->create(['name' => 'Won One']);
        Lead::factory()->withStatus($lost)->create(['name' => 'Lost One']);

        $this->actingAs($admin);
        $this->get('/leads?stage=fresh')->assertSee('Fresh One')->assertDontSee('Working One')->assertDontSee('Won One');
        $this->get('/leads?stage=working')->assertSee('Working One')->assertDontSee('Fresh One')->assertDontSee('Sleepy One');
        $this->get('/leads?stage=dormant')->assertSee('Sleepy One')->assertDontSee('Working One');
        $this->get('/leads?stage=due')->assertSee('Due One')->assertDontSee('Fresh One');
        $this->get('/leads?stage=won')->assertSee('Won One')->assertDontSee('Lost One');
        $this->get('/leads?stage=lost')->assertSee('Lost One')->assertDontSee('Won One');
    }

    public function test_search_finds_leads_by_name_phone_or_company(): void
    {
        $admin = $this->registerOrganization();
        Lead::factory()->for($admin->organization)->create(['name' => 'Priya Sharma', 'company' => 'Globex']);
        Lead::factory()->for($admin->organization)->create(['name' => 'Other Person', 'company' => 'Initech']);

        $this->actingAs($admin)->get('/leads?q=globex')->assertSee('Priya Sharma')->assertDontSee('Other Person');
    }

    public function test_dashboard_renders_for_admins_and_agents(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        Lead::factory()->for($admin->organization)->count(3)->create(['assigned_to' => $agent->id]);

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Team')->assertSee($agent->name);
        $this->actingAs($agent)->get('/dashboard')->assertOk()->assertDontSee('Team');
    }
}
