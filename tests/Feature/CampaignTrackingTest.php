<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Integrations\FacebookLeadAds;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = $this->registerOrganization();
    }

    private function integration(IntegrationType $type, array $settings): Integration
    {
        $integration = new Integration(['type' => $type, 'settings' => $settings]);
        $integration->organization_id = $this->admin->organization_id;
        $integration->save();

        return $integration;
    }

    private function adClick(string $id, string $headline, string $adId): array
    {
        return ['from' => '919876543210', 'id' => $id, 'type' => 'text', 'text' => ['body' => 'Hi, I want to know more'], 'referral' => [
            'source_url' => 'https://fb.me/abc', 'source_id' => $adId, 'source_type' => 'ad',
            'headline' => $headline, 'body' => 'Flats from 45L', 'ctwa_clid' => "clid-{$adId}",
        ]];
    }

    public function test_a_click_to_whatsapp_ad_is_kept_as_the_leads_first_touch(): void
    {
        $whatsapp = $this->connectWhatsApp($this->admin->organization);

        $this->whatsAppWebhook($whatsapp, [$this->adClick('wamid.1', 'Diwali offer: 2BHK Baner', '1201')], 'Priya')->assertOk();
        $this->whatsAppWebhook($whatsapp, [$this->adClick('wamid.2', 'Another ad', '9999')])->assertOk();

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame(['Diwali offer: 2BHK Baner', '1201', 'clid-1201'], [$lead->campaign, $lead->ad_id, $lead->click_id]);
        $this->assertSame('WhatsApp ad', $lead->source()->withoutGlobalScopes()->first()->name);
        $this->actingAs($this->admin)->get("/leads/{$lead->id}")->assertSee('Diwali offer: 2BHK Baner')->assertSee('Ad 1201');
    }

    public function test_a_lead_added_by_hand_gets_the_campaign_it_later_comes_through(): void
    {
        $whatsapp = $this->connectWhatsApp($this->admin->organization);
        $lead = Lead::factory()->for($this->admin->organization)->create(['phone' => '+919876543210']);

        $this->whatsAppWebhook($whatsapp, [$this->adClick('wamid.1', 'Diwali offer', '1201')])->assertOk();

        $this->assertSame('Diwali offer', $lead->fresh()->campaign);
    }

    public function test_facebook_and_google_lead_ads_record_their_campaign(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'L1', 'ad_id' => '555', 'ad_name' => 'Carousel A', 'campaign_name' => 'Monsoon sale', 'field_data' => [
            ['name' => 'full_name', 'values' => ['Meera Nair']], ['name' => 'phone_number', 'values' => ['+919811122233']],
        ]])]);
        $facebook = $this->integration(IntegrationType::Facebook, ['page_access_token' => 't', 'app_secret' => 'fb-secret', 'verify_token' => 'v']);
        app(FacebookLeadAds::class)->import($facebook, 'L1');

        $google = $this->integration(IntegrationType::Google, ['google_key' => 'g-key']);
        $this->postJson("/api/webhooks/google/{$google->webhook_key}", [
            'lead_id' => 'gl-1', 'google_key' => 'g-key', 'campaign_id' => 7781, 'creative_id' => 42, 'gcl_id' => 'gclid-abc',
            'user_column_data' => [['column_id' => 'FULL_NAME', 'string_value' => 'Arjun'], ['column_id' => 'PHONE_NUMBER', 'string_value' => '+919822233344']],
        ])->assertOk();

        $this->assertSame(['Monsoon sale', '555'], [Lead::withoutGlobalScopes()->where('name', 'Meera Nair')->sole()->campaign, Lead::withoutGlobalScopes()->where('name', 'Meera Nair')->sole()->ad_id]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'fields=field_data%2Cad_id%2Cad_name%2Ccampaign_name'));
        $arjun = Lead::withoutGlobalScopes()->where('name', 'Arjun')->sole();
        $this->assertSame(['Google Ads campaign 7781', '42', 'gclid-abc'], [$arjun->campaign, $arjun->ad_id, $arjun->click_id]);
    }

    public function test_campaign_links_work_for_the_web_form_and_the_api(): void
    {
        $form = $this->integration(IntegrationType::WebForm, ['title' => 'Enquire']);

        $this->get("/f/{$form->webhook_key}?utm_campaign=insta-reel-oct&fbclid=fb123")
            ->assertSee('name="utm_campaign" value="insta-reel-oct"', false)->assertSee('name="fbclid" value="fb123"', false);
        $this->post("/f/{$form->webhook_key}", ['name' => 'Kiran', 'phone' => '+919000011111', 'utm_campaign' => 'insta-reel-oct', 'fbclid' => 'fb123'])->assertOk();

        $key = app(ApiKeyManager::class)->regenerate($this->admin->organization);
        $this->postJson('/api/v1/leads', ['name' => 'Web', 'phone' => '9876500000', 'utm_campaign' => 'google-brand', 'gclid' => 'g-1'], ['X-Api-Key' => $key])->assertCreated();

        $this->assertSame(['insta-reel-oct', 'fb123'], [Lead::withoutGlobalScopes()->where('name', 'Kiran')->sole()->campaign, Lead::withoutGlobalScopes()->where('name', 'Kiran')->sole()->click_id]);
        $this->assertSame('google-brand', Lead::withoutGlobalScopes()->where('name', 'Web')->sole()->campaign);
    }

    public function test_reports_compare_campaigns_and_link_to_their_leads(): void
    {
        $won = LeadStatus::query()->withoutGlobalScopes()->where('organization_id', $this->admin->organization_id)->where('name', 'Won')->sole();
        Lead::factory()->count(3)->for($this->admin->organization)->create(['campaign' => 'Diwali offer']);
        Lead::factory()->for($this->admin->organization)->create(['campaign' => 'Diwali offer', 'status_id' => $won->id, 'value' => 500000, 'closed_at' => now()]);
        Lead::factory()->for($this->admin->organization)->create(['campaign' => 'Monsoon sale']);
        Lead::factory()->for($this->admin->organization)->create(['name' => 'No Campaign Lead']);

        $this->actingAs($this->admin)->get('/reports')->assertOk()
            ->assertSee('Campaigns & ads', false)
            ->assertSeeInOrder(['Diwali offer', '4', '1', '25%', 'Monsoon sale'])
            ->assertSee(route('leads.index', ['campaign' => 'Diwali offer']), false);

        $this->get('/leads?campaign=Monsoon+sale')->assertOk()->assertDontSee('No Campaign Lead')->assertSee('value="Monsoon sale"', false);
    }
}
