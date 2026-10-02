<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\RoutingRule;
use App\Models\Source;
use App\Models\User;
use App\Services\LeadAssigner;
use App\Services\LeadIntake;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->registerOrganization();
        $this->organization = $this->admin->organization;
        $this->actingAs($this->admin); // rules are created inside the admin's workspace
    }

    private function incoming(array $data = [], string $source = 'Website'): Lead
    {
        static $phone = 9800000000;
        $phone++;

        return app(LeadIntake::class)->capture($this->organization, $data + ['name' => 'Lead '.$phone, 'phone' => '+91'.$phone], $source)->lead;
    }

    private function rule(string $name, array $conditions, array $people, int $position = 1): RoutingRule
    {
        return RoutingRule::create(['name' => $name, 'position' => $position, 'conditions' => $conditions, 'agent_ids' => array_map(fn (User $u) => $u->id, $people), 'is_active' => true]);
    }

    public function test_matching_leads_go_to_the_rules_group_in_turn_and_the_rest_to_everyone(): void
    {
        [$asha, $ravi, $neha] = [$this->addAgent($this->organization), $this->addAgent($this->organization), $this->addAgent($this->organization)];
        $facebook = Source::query()->create(['name' => 'Facebook']);
        $this->rule('Facebook team', ['source_id' => $facebook->id], [$asha, $ravi]);

        $owners = collect(range(1, 4))->map(fn () => $this->incoming([], 'Facebook')->assigned_to)->all();
        $this->assertSame([$asha->id, $ravi->id, $asha->id, $ravi->id], $owners);

        $this->assertSame($asha->id, $this->incoming()->assigned_to, 'no rule: everyone takes turns');
        $this->assertSame($ravi->id, $this->incoming()->assigned_to);
        $this->assertSame($neha->id, $this->incoming()->assigned_to);
    }

    public function test_city_and_custom_field_conditions_ignore_capital_letters(): void
    {
        [$general, $puneTeam] = [$this->addAgent($this->organization), $this->addAgent($this->organization)];
        CustomField::create(['label' => 'Plan', 'key' => 'plan', 'type' => 'text', 'sort_order' => 1]);
        $this->rule('Pune Pro', ['city' => 'Pune', 'field_key' => 'plan', 'field_value' => 'Pro'], [$puneTeam]);
        $create = fn (string $phone, string $city, string $plan) => app(LeadService::class)->create($this->organization, [
            'name' => 'Lead', 'phone' => $phone, 'city' => $city, 'custom_values' => ['plan' => $plan],
        ]);

        $this->assertSame($puneTeam->id, $create('+919811111111', ' pune ', 'PRO')->assigned_to);
        $this->assertSame($general->id, $create('+919811111112', 'Mumbai', 'Pro')->assigned_to, 'no match: the general rotation');
    }

    public function test_away_and_full_agents_are_skipped_but_a_lead_is_never_left_without_an_owner(): void
    {
        [$asha, $ravi] = [$this->addAgent($this->organization), $this->addAgent($this->organization)];
        $ravi->forceFill(['is_available' => false])->save();

        $this->assertSame([$asha->id, $asha->id], [$this->incoming()->assigned_to, $this->incoming()->assigned_to]);

        $this->organization->forceFill(['max_open_leads' => 2])->save();
        $ravi->forceFill(['is_available' => true])->save();
        $this->assertSame($ravi->id, $this->incoming()->assigned_to, 'Asha has 2 open leads: at the limit');

        $asha->forceFill(['is_available' => false])->save();
        $ravi->forceFill(['is_available' => false])->save();
        $this->assertContains($this->incoming()->assigned_to, [$asha->id, $ravi->id], 'everyone away: still someone');
        $this->assertSame($asha->id, app(LeadAssigner::class)->assigneeFor($this->organization, $this->admin, $asha->id), 'an admin\'s own choice always wins');
    }

    public function test_the_first_matching_rule_wins_and_order_and_pause_are_respected(): void
    {
        [$asha, $ravi] = [$this->addAgent($this->organization), $this->addAgent($this->organization)];
        $first = $this->rule('Everyone from Pune', ['city' => 'Pune'], [$asha], 1);
        $second = $this->rule('Pune website', ['city' => 'Pune'], [$ravi], 2);

        $this->assertSame($asha->id, $this->incoming(['city' => 'Pune'])->assigned_to);

        $this->post("/settings/routing/rules/{$second->id}/move/up")->assertRedirect();
        $this->assertSame([1, 2], [$second->fresh()->position, $first->fresh()->position]);
        $this->assertSame($ravi->id, $this->incoming(['city' => 'Pune'])->assigned_to);

        $this->post("/settings/routing/rules/{$second->id}/toggle");
        $this->assertSame($asha->id, $this->incoming(['city' => 'Pune'])->assigned_to, 'a paused rule is skipped');
    }

    public function test_admins_manage_rules_and_availability_and_people_set_their_own(): void
    {
        $agent = $this->addAgent($this->organization, ['name' => 'Asha']);
        $source = Source::query()->where('name', 'Website')->sole();

        $this->get('/settings/routing')->assertOk()->assertSee('No routing rules');
        $this->post('/settings/routing/rules', ['name' => 'Everything', 'agent_ids' => [$agent->id]])->assertSessionHasErrors('conditions');
        $this->post('/settings/routing/rules', ['name' => 'Web', 'conditions' => ['source_id' => $source->id]])->assertSessionHasErrors('agent_ids');
        $this->post('/settings/routing/rules', ['name' => 'Web', 'conditions' => ['source_id' => $source->id], 'agent_ids' => [$agent->id]])->assertRedirect('/settings/routing');
        $this->get('/settings/routing')->assertSee('If source is <b>Website</b> → Asha', false);

        $this->put('/settings/routing/limit', ['max_open_leads' => 25]);
        $this->assertSame(25, $this->organization->fresh()->max_open_leads);

        $this->post("/settings/routing/people/{$agent->id}/availability")->assertSessionHas('status', 'Asha is away: new leads skip them.');
        $this->assertFalse($agent->fresh()->is_available);
        $outsider = $this->registerOrganization('Other')->organization->users()->first();
        $this->post("/settings/routing/people/{$outsider->id}/availability")->assertNotFound();

        $this->actingAs($agent->fresh())->post('/availability')->assertSessionHas('status', 'Welcome back: you will get new leads again.');
        $this->assertTrue($agent->fresh()->is_available);
        $this->get('/settings/routing')->assertForbidden();
    }

    public function test_passing_a_lead_on_skips_people_who_are_away(): void
    {
        [$asha, $ravi, $neha] = [$this->addAgent($this->organization), $this->addAgent($this->organization), $this->addAgent($this->organization)];
        $ravi->forceFill(['is_available' => false])->save();

        $this->assertSame($neha->id, app(LeadAssigner::class)->nextInRotation($this->organization->fresh(), except: $asha->id));
    }
}
