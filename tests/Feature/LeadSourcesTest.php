<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_set_up_the_hosted_form_and_visitors_submit_it(): void
    {
        $admin = $this->registerOrganization('Sunrise Realty');
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($admin)->put('/settings/integrations/web_form', [
            'title' => 'Book a site visit', 'button' => 'Book now', 'thank_you' => 'We will call you today.',
            'ask_email' => '1', 'ask_message' => '1',
        ])->assertRedirect('/settings/integrations/web_form');
        $form = Integration::sole();
        $this->post('/logout');

        $this->get("/f/{$form->webhook_key}")->assertOk()->assertSee('Book a site visit')->assertSee('Sunrise Realty');

        $this->post("/f/{$form->webhook_key}", ['name' => 'Kiran', 'phone' => '+91 90000 11111', 'message' => '3BHK please'])
            ->assertOk()->assertSee('We will call you today.');

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('+919000011111', $lead->phone);
        $this->assertSame('3BHK please', $lead->notes);
        $this->assertSame($agent->id, $lead->assigned_to);
        $this->assertSame('Website form', $lead->source()->withoutGlobalScopes()->first()->name);
    }

    public function test_the_form_rejects_bad_input_and_silently_drops_bots(): void
    {
        $form = $this->integration($this->registerOrganization()->organization, IntegrationType::WebForm, ['title' => 'Hi']);

        $this->post("/f/{$form->webhook_key}", ['name' => '', 'phone' => '12'])->assertOk()
            ->assertSee('The name field is required.')->assertSee('Please enter a valid phone number.');
        $this->post("/f/{$form->webhook_key}", ['name' => 'Bot', 'phone' => '9000011111', 'website' => 'http://spam'])->assertOk();

        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
        $this->get('/f/not-a-real-key')->assertOk()->assertSee('not available');
    }

    public function test_a_repeat_enquiry_is_added_to_the_existing_lead(): void
    {
        $organization = $this->registerOrganization()->organization;
        $form = $this->integration($organization, IntegrationType::WebForm, ['title' => 'Hi']);
        Lead::factory()->for($organization)->create(['phone' => '+919000011111']);

        $this->post("/f/{$form->webhook_key}", ['name' => 'Kiran', 'phone' => '+919000011111', 'message' => 'Still interested'])->assertOk();

        $this->assertSame(1, Lead::withoutGlobalScopes()->count());
        $this->assertStringContainsString('New enquiry via Website form. Still interested', LeadActivity::withoutGlobalScopes()->sole()->note);
    }

    public function test_facebook_lead_ads_are_fetched_and_captured(): void
    {
        Http::fake(['graph.facebook.com/v25.0/LEAD123' => Http::response(['id' => 'LEAD123', 'field_data' => [
            ['name' => 'full_name', 'values' => ['Meera Nair']],
            ['name' => 'phone_number', 'values' => ['+919811122233']],
            ['name' => 'email', 'values' => ['meera@example.com']],
            ['name' => 'which_course?', 'values' => ['MBA']],
        ]])]);
        $organization = $this->registerOrganization()->organization;
        $facebook = $this->integration($organization, IntegrationType::Facebook, ['page_access_token' => 'page-token', 'app_secret' => 'fb-secret', 'verify_token' => 'v']);
        $payload = ['object' => 'page', 'entry' => [['changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => 'LEAD123', 'page_id' => '1']]]]]];

        $this->metaWebhook($facebook, $payload, 'fb-secret')->assertOk();
        $this->metaWebhook($facebook, $payload, 'fb-secret')->assertOk();

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Meera Nair', $lead->name);
        $this->assertSame('meera@example.com', $lead->email);
        $this->assertSame('Which course?: MBA', $lead->notes);
        $this->assertSame('Facebook Ads', $lead->source()->withoutGlobalScopes()->first()->name);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer page-token'));
        $this->assertSame(0, LeadActivity::withoutGlobalScopes()->count(), 'The retried webhook must not add a second enquiry.');
    }

    public function test_google_ads_leads_require_the_shared_key(): void
    {
        $organization = $this->registerOrganization()->organization;
        $google = $this->integration($organization, IntegrationType::Google, ['google_key' => 'g-key']);
        $payload = [
            'lead_id' => 'gl-1', 'google_key' => 'g-key', 'is_test' => true,
            'user_column_data' => [
                ['column_id' => 'FULL_NAME', 'string_value' => 'Arjun Rao'],
                ['column_id' => 'PHONE_NUMBER', 'string_value' => '+919822233344'],
                ['column_id' => 'EMAIL', 'string_value' => 'arjun@example.com'],
            ],
        ];

        $this->postJson("/api/webhooks/google/{$google->webhook_key}", ['google_key' => 'wrong'] + $payload)->assertForbidden();
        $this->postJson("/api/webhooks/google/{$google->webhook_key}", $payload)->assertOk();
        $this->postJson("/api/webhooks/google/{$google->webhook_key}", $payload)->assertOk();

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Arjun Rao', $lead->name);
        $this->assertStringContainsString('Test lead sent from Google Ads', $lead->notes);
        $this->assertSame(0, LeadActivity::withoutGlobalScopes()->count());
    }

    public function test_lead_ads_need_a_plan_that_includes_them(): void
    {
        $admin = $this->registerOrganization();
        $admin->organization->forceFill(['plan' => 'starter', 'subscription_status' => 'active'])->save();

        $this->actingAs($admin->fresh())->put('/settings/integrations/google')->assertRedirect('/settings/billing');
        $this->assertSame(0, Integration::count());

        $this->get('/settings/integrations')->assertOk()->assertSee('Upgrade');
    }

    private function integration(Organization $organization, IntegrationType $type, array $settings): Integration
    {
        $integration = new Integration(['type' => $type, 'settings' => $settings]);
        $integration->organization_id = $organization->id;
        $integration->save();

        return $integration;
    }

    private function metaWebhook(Integration $integration, array $payload, string $secret)
    {
        $json = json_encode($payload);

        return $this->call('POST', "/api/webhooks/meta/{$integration->webhook_key}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, $secret),
        ], $json);
    }
}
