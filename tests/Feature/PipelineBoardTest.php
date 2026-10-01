<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineBoardTest extends TestCase
{
    use RefreshDatabase;

    private function stage(Organization $organization, string $name): LeadStatus
    {
        return $organization->leadStatuses()->where('name', $name)->sole();
    }

    public function test_the_board_shows_a_column_per_stage_with_counts_and_value(): void
    {
        $admin = $this->registerOrganization();
        $organization = $admin->organization;
        Lead::factory()->for($organization)->create(['name' => 'Priya Sharma', 'status_id' => $this->stage($organization, 'Interested')->id, 'value' => 240000]);
        Lead::factory()->for($organization)->create(['name' => 'Arjun Mehta', 'status_id' => $this->stage($organization, 'Interested')->id, 'value' => 60000]);
        Lead::factory()->for($organization)->create(['name' => 'Kiran Rao', 'status_id' => $this->stage($organization, 'New')->id, 'value' => null]);

        $response = $this->actingAs($admin)->get('/leads?view=board')->assertOk()
            ->assertSee('Priya Sharma')->assertSee('Kiran Rao')->assertSee('₹2,40,000')
            ->assertSee('<strong>₹3L</strong> in the pipeline', false);

        $interested = $response->viewData('columns')->firstWhere('status.name', 'Interested');
        $this->assertSame(2, $interested->count);
        $this->assertSame(300000.0, $interested->value);
    }

    public function test_board_filters_apply_and_agents_only_see_their_own_cards(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        Lead::factory()->for($admin->organization)->create(['name' => 'Mine Lead', 'assigned_to' => $agent->id]);
        Lead::factory()->for($admin->organization)->create(['name' => 'Other Lead', 'assigned_to' => $admin->id]);

        $this->actingAs($admin)->get('/leads?view=board&q=Mine')->assertSee('Mine Lead')->assertDontSee('Other Lead');
        $this->actingAs($agent)->get('/leads?view=board')->assertSee('Mine Lead')->assertDontSee('Other Lead');
    }

    public function test_moving_a_lead_changes_its_stage_logs_it_and_keeps_the_follow_up(): void
    {
        $admin = $this->registerOrganization();
        $followUp = now()->addDays(2)->startOfMinute();
        $lead = Lead::factory()->for($admin->organization)->create(['next_follow_up_at' => $followUp]);
        $interested = $this->stage($admin->organization, 'Interested');

        $this->actingAs($admin)
            ->patchJson("/leads/{$lead->id}/status", ['status_id' => $interested->id])
            ->assertOk()
            ->assertJson(['status' => ['id' => $interested->id, 'name' => 'Interested']]);

        $lead->refresh();
        $this->assertSame($interested->id, $lead->status_id);
        $this->assertTrue($lead->next_follow_up_at->equalTo($followUp));
        $this->assertSame($interested->id, $lead->activities()->sole()->status_id);
    }

    public function test_the_stage_bar_moves_a_lead_and_returns_to_the_page(): void
    {
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();
        $won = $this->stage($admin->organization, 'Won');

        $this->actingAs($admin)->from("/leads/{$lead->id}")
            ->patch("/leads/{$lead->id}/status", ['status_id' => $won->id])
            ->assertRedirect("/leads/{$lead->id}")
            ->assertSessionHas('status', 'Moved to Won.');

        $this->assertNotNull($lead->fresh()->closed_at);
        $this->get("/leads/{$lead->id}")->assertOk()->assertDontSee('Mark won');
    }

    public function test_leads_cannot_be_moved_by_other_agents_or_into_another_workspaces_stage(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $lead = Lead::factory()->for($admin->organization)->create(['assigned_to' => $admin->id]);
        $contacted = $this->stage($admin->organization, 'Contacted');
        $foreign = $this->stage($this->registerOrganization('Other')->organization, 'Contacted');

        $this->actingAs($agent)->patchJson("/leads/{$lead->id}/status", ['status_id' => $contacted->id])->assertForbidden();
        $this->actingAs($admin)->patchJson("/leads/{$lead->id}/status", ['status_id' => $foreign->id])->assertUnprocessable();
        $this->assertNotSame($contacted->id, $lead->fresh()->status_id);
    }

    public function test_the_dashboard_shows_pipeline_value_and_the_sidebar_counts_follow_ups(): void
    {
        $admin = $this->registerOrganization();
        Lead::factory()->for($admin->organization)->create(['value' => 1250000, 'next_follow_up_at' => now()->subDay()]);

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('₹12.5L')
            ->assertSee('1 follow-up overdue')
            ->assertSee('<span class="count hot">1</span>', false);
    }

    public function test_money_reads_the_indian_way(): void
    {
        $this->assertSame('₹2,40,000', Money::full(240000));
        $this->assertSame('₹1,24,00,000', Money::full(12400000));
        $this->assertSame('₹999', Money::full(999));
        $this->assertSame('₹12.4L', Money::short(1240000));
        $this->assertSame('₹1.2Cr', Money::short(12400000));
        $this->assertSame('₹45K', Money::short(45000));
    }
}
