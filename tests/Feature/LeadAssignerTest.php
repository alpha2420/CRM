<?php

namespace Tests\Feature;

use App\Services\LeadAssigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadAssignerTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_robin_cycles_through_active_agents_only(): void
    {
        $admin = $this->registerOrganization();
        $organization = $admin->organization;
        $first = $this->addAgent($organization);
        $this->addAgent($organization, ['is_active' => false]);
        $second = $this->addAgent($organization);
        $assigner = app(LeadAssigner::class);

        $picks = collect(range(1, 4))->map(fn () => $assigner->assigneeFor($organization, $admin, null));

        $this->assertSame([$first->id, $second->id, $first->id, $second->id], $picks->all());
    }

    public function test_explicit_choice_by_admin_is_kept_and_agents_keep_their_own(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $assigner = app(LeadAssigner::class);

        $this->assertSame($admin->id, $assigner->assigneeFor($admin->organization, $admin, $admin->id));
        $this->assertSame($agent->id, $assigner->assigneeFor($admin->organization, $agent, $admin->id));
    }

    public function test_no_agents_means_unassigned(): void
    {
        $admin = $this->registerOrganization();

        $this->assertNull(app(LeadAssigner::class)->assigneeFor($admin->organization, null, null));
    }
}
