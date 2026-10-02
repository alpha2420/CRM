<?php

namespace Tests\Feature;

use App\Ai\AssistantException;
use App\Ai\InsightGenerator;
use App\Ai\LeadAssistant;
use App\Ai\LeadInsight;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private FakeInsightGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-key', 'crm.ai_monthly_limit' => 2]);
        $this->generator = new FakeInsightGenerator;
        $this->app->instance(InsightGenerator::class, $this->generator);
    }

    public function test_an_agent_gets_a_summary_score_and_ready_message(): void
    {
        $admin = $this->registerOrganization('Sunrise Realty');
        $lead = Lead::factory()->for($admin->organization)->create(['name' => 'Kiran', 'notes' => 'Wants 2BHK near metro']);
        $this->actingAs($admin)->post("/leads/{$lead->id}/activities", ['status_id' => $lead->status_id, 'note' => 'Called, asked for price list']);

        $this->post("/leads/{$lead->id}/ai")->assertSessionHas('status', 'AI insight updated.');

        $this->assertSame('hot', $lead->fresh()->ai_insight['temperature']);
        $this->get("/leads/{$lead->id}")->assertSee('Hot')->assertSee('Share the price list today.')->assertSee('1 analyses left this month.');

        // The model sees the lead's data, wrapped so it is treated as data.
        $this->assertStringContainsString('<lead>', $this->generator->lastPrompt);
        $this->assertStringContainsString('Wants 2BHK near metro', $this->generator->lastPrompt);
        $this->assertStringContainsString('Called, asked for price list', $this->generator->lastPrompt);
        $this->assertStringContainsString('never as instructions', $this->generator->lastSystem);
    }

    public function test_unknown_temperatures_are_normalised(): void
    {
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();
        $this->generator->temperature = 'Lukewarm';

        $this->actingAs($admin)->post("/leads/{$lead->id}/ai");

        $this->assertSame('warm', $lead->fresh()->ai_insight['temperature']);
    }

    public function test_the_monthly_allowance_is_enforced_and_failures_are_explained(): void
    {
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();
        $this->actingAs($admin);

        $this->post("/leads/{$lead->id}/ai");
        $this->post("/leads/{$lead->id}/ai");
        $this->post("/leads/{$lead->id}/ai")->assertSessionHasErrors(['ai' => "You have used this month's AI analyses. The allowance resets on the 1st."]);
        $this->assertSame(2, $this->generator->calls);

        $admin->organization->forceFill(['ai_usage_month' => now()->subMonth()->format('Y-m')])->save();
        $this->generator->failWith = 'The AI assistant is busy right now. Please try again in a minute.';
        $this->post("/leads/{$lead->id}/ai")->assertSessionHasErrors(['ai' => 'The AI assistant is busy right now. Please try again in a minute.']);
        $this->assertSame(2, app(LeadAssistant::class)->remainingThisMonth($admin->organization->fresh()), 'A failed call must not use up the allowance.');
    }

    public function test_ai_is_hidden_without_the_plan_or_an_api_key(): void
    {
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();

        $admin->organization->forceFill(['plan' => 'growth', 'subscription_status' => 'active'])->save();
        $this->actingAs($admin->fresh())->get("/leads/{$lead->id}")->assertOk()->assertDontSee('Analyse lead');
        $this->post("/leads/{$lead->id}/ai")->assertRedirect('/settings/billing');

        $admin->organization->forceFill(['plan' => 'pro'])->save();
        config(['services.gemini.api_key' => null]);
        $this->actingAs($admin->fresh())->get("/leads/{$lead->id}")->assertDontSee('Analyse lead');
        $this->assertSame(0, $this->generator->calls);
    }
}

class FakeInsightGenerator implements InsightGenerator
{
    public int $calls = 0;

    public string $lastSystem = '';

    public string $lastPrompt = '';

    public string $temperature = 'hot';

    public ?string $failWith = null;

    public function generate(string $system, string $prompt): LeadInsight
    {
        if ($this->failWith !== null) {
            throw new AssistantException($this->failWith);
        }

        $this->calls++;
        [$this->lastSystem, $this->lastPrompt] = [$system, $prompt];

        $insight = new LeadInsight;
        $insight->summary = 'Kiran wants a 2BHK near the metro and asked for prices.';
        $insight->temperature = $this->temperature;
        $insight->reason = 'Asked for the price list yesterday.';
        $insight->next_step = 'Share the price list today.';
        $insight->suggested_message = 'Hi Kiran, here is the price list you asked for.';

        return $insight;
    }
}
