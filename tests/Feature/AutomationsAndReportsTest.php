<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Enums\StatusType;
use App\Models\Automation;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomationsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_lead_from_a_source_is_routed_greeted_and_scheduled(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.auto']]])]);
        $admin = $this->registerOrganization('Acme');
        $organization = $admin->organization;
        $priya = $this->addAgent($organization, ['name' => 'Priya']);
        $this->addAgent($organization, ['name' => 'Ravi']);
        $template = $this->whatsAppWithTemplate($organization);
        $referral = Source::where('name', 'Referral')->first();

        $this->actingAs($admin)->post('/settings/automations', [
            'name' => 'Referral welcome',
            'trigger' => 'lead_created',
            'conditions' => ['source_id' => $referral->id],
            'actions' => ['assign_to' => $priya->id, 'whatsapp_template_id' => $template->id, 'follow_up_in_hours' => 2],
        ])->assertRedirect('/settings/automations');
        $this->get('/settings/automations')->assertSee('Referral welcome')->assertSee('Priya');
        $this->post('/logout');

        $key = app(ApiKeyManager::class)->regenerate($organization);
        $this->postJson('/api/v1/leads', ['name' => 'Kiran Rao', 'phone' => '9811100001', 'source' => 'Referral'], ['X-Api-Key' => $key])->assertCreated();
        $this->postJson('/api/v1/leads', ['name' => 'Other', 'phone' => '9811100002', 'source' => 'Website'], ['X-Api-Key' => $key])->assertCreated();

        $lead = Lead::withoutGlobalScopes()->where('phone', '+919811100001')->sole();
        $this->assertSame($priya->id, $lead->assigned_to);
        $this->assertEqualsWithDelta(now()->addHours(2)->timestamp, $lead->next_follow_up_at->timestamp, 60);
        $this->assertSame('Hi Kiran, thanks for contacting Acme!', WhatsAppMessage::withoutGlobalScopes()->sole()->body);
        $this->assertSame(1, Automation::withoutGlobalScopes()->sole()->runs);
    }

    public function test_status_change_automations_run_once_and_never_loop(): void
    {
        $admin = $this->registerOrganization();
        $interested = LeadStatus::where('name', 'Interested')->first();
        $meeting = LeadStatus::where('name', 'Meeting Done')->first();
        $this->actingAs($admin);

        // Two rules that would ping-pong forever if automations could trigger each other.
        $this->post('/settings/automations', ['name' => 'A', 'trigger' => 'status_changed', 'conditions' => ['status_id' => $interested->id], 'actions' => ['set_status_id' => $meeting->id]]);
        $this->post('/settings/automations', ['name' => 'B', 'trigger' => 'status_changed', 'conditions' => ['status_id' => $meeting->id], 'actions' => ['set_status_id' => $interested->id]]);
        $lead = Lead::factory()->for($admin->organization)->create();

        $this->post("/leads/{$lead->id}/activities", ['status_id' => $interested->id])->assertRedirect();

        $this->assertSame($meeting->id, $lead->fresh()->status_id);
        $this->assertSame([1, 0], Automation::orderBy('name')->pluck('runs')->all());
        $this->get("/leads/{$lead->id}")->assertSee('Status set by automation');
    }

    public function test_an_automation_needs_an_action_and_a_plan_that_includes_it(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin)->post('/settings/automations', ['name' => 'Empty', 'trigger' => 'lead_created'])->assertSessionHasErrors('actions');

        $admin->organization->forceFill(['plan' => 'starter', 'subscription_status' => 'active'])->save();
        $this->actingAs($admin->fresh())->get('/settings/automations')->assertRedirect('/settings/billing');
    }

    public function test_reports_show_the_funnel_speed_to_lead_and_sources(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization, ['name' => 'Priya']);
        $won = LeadStatus::where('type', StatusType::Won)->first();
        $website = Source::where('name', 'Website')->first();

        $fast = Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id, 'source_id' => $website->id]);
        $fast->forceFill(['created_at' => now()->subDays(2), 'first_contacted_at' => now()->subDays(2)->addMinutes(30), 'created_by' => null, 'response_seconds' => 1800])->save();
        $fast->update(['status_id' => $won->id, 'value' => 50000]);
        Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id, 'source_id' => $website->id]);
        Lead::factory()->for($admin->organization)->create(['created_at' => now()->subDays(60)]);

        $response = $this->actingAs($admin)->get('/reports?range=30')->assertOk();
        $report = $response->viewData('report');

        $this->assertSame(['New' => 2, 'Contacted' => 1, 'Won' => 1], $report['funnel']);
        $this->assertSame(1800, $report['kpis']['speed']['median']);
        $this->assertSame(1, $report['kpis']['won']);
        $this->assertSame(50000.0, $report['kpis']['won_value']);
        $this->assertSame('Website', $report['sources'][0]['name']);
        $this->assertSame(50.0, $report['sources'][0]['win_rate']);
        $response->assertSee('Priya')->assertSee('30m');

        $this->get('/reports?range=custom&from=2020-01-01&to=2019-01-01')->assertSessionHasErrors('to');
        $this->actingAs($agent)->get('/reports')->assertForbidden();
    }

    private function whatsAppWithTemplate($organization): WhatsAppTemplate
    {
        $integration = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => [
            'phone_number_id' => 'P', 'waba_id' => 'W', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v', 'default_country_code' => '91',
        ]]);
        $integration->organization_id = $organization->id;
        $integration->save();

        $template = new WhatsAppTemplate(['name' => 'welcome', 'language' => 'en', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, thanks for contacting {{2}}!', 'variables' => 2]);
        $template->organization_id = $organization->id;
        $template->save();

        return $template;
    }
}
