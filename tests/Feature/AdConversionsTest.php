<?php

namespace Tests\Feature;

use App\Models\AdConversion;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdConversionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Integration $whatsapp;

    /** @var list<array<string, mixed>> events Meta received */
    private array $received = [];

    /** When true, Meta refuses everything (see setUp). */
    private bool $metaRefuses = false;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->registerOrganization();
        $this->whatsapp = $this->connectWhatsApp($this->admin->organization);

        Http::fake(function (Request $request) {
            if ($this->metaRefuses && str_contains($request->url(), 'graph.facebook.com') && ! str_contains($request->url(), '/messages')) {
                return str_ends_with($request->url(), '/dataset')
                    ? Http::response(['error' => ['message' => '(#200) Permissions error']], 403)
                    : Http::response(['error' => ['message' => 'Invalid parameter', 'error_user_msg' => 'The ctwa_clid is not valid.']], 400);
            }
            if (str_ends_with($request->url(), '/WABA_ID/dataset')) {
                return Http::response(['id' => 'DATASET_1']);
            }
            if (str_ends_with($request->url(), '/DATASET_1/events')) {
                array_push($this->received, ...$request['data']);

                return Http::response(['events_received' => count($request['data'])]);
            }

            return Http::response(['messages' => [['id' => 'wamid.out']]]); // WhatsApp sends
        });
    }

    private function turnOn(): void
    {
        $this->actingAs($this->admin)->post('/settings/integrations/whatsapp/conversions')
            ->assertSessionHas('status', 'Ad results are on: Meta will hear which ad leads qualify and buy.');
    }

    private function adLead(): Lead
    {
        $this->whatsAppWebhook($this->whatsapp, [['from' => '919876543210', 'id' => 'wamid.1', 'type' => 'text', 'text' => ['body' => 'Hi'],
            'referral' => ['source_type' => 'ad', 'source_id' => '1201', 'headline' => 'Diwali offer', 'ctwa_clid' => 'ARAkLkA8rmlF']]], 'Priya')->assertOk();

        return Lead::withoutGlobalScopes()->where('phone', '+919876543210')->sole();
    }

    private function move(Lead $lead, string $stage, array $extra = []): void
    {
        $lead->refresh()->update($extra);
        $this->actingAs($this->admin)->patch("/leads/{$lead->id}/status", ['status_id' => LeadStatus::query()->where('name', $stage)->sole()->id]);
    }

    public function test_meta_hears_when_an_ad_lead_arrives_qualifies_and_buys_once_each_and_never_who_they_are(): void
    {
        $this->freezeTime(); // event_time is compared to the second
        $this->turnOn();
        $settings = Integration::query()->where('type', 'whatsapp')->sole()->settings;
        $this->assertSame(['DATASET_1', true], [$settings['dataset_id'], $settings['conversions_on']]);
        $this->assertSame(LeadStatus::query()->where('name', 'Interested')->value('id'), $settings['qualified_status_id'], 'Interested counts as qualified by default');

        $lead = $this->adLead();
        $this->assertSame('ctwa', $lead->click_type);

        $this->move($lead, 'Contacted');
        $this->move($lead, 'Interested');
        $this->move($lead, 'Meeting Done');
        $this->move($lead, 'Won', ['value' => 250000]);

        $this->assertSame(['LeadSubmitted', 'QualifiedLead', 'Purchase'], array_column($this->received, 'event_name'));
        $this->assertSame([
            'event_name' => 'Purchase',
            'event_time' => now()->timestamp,
            'event_id' => "lead-{$lead->id}-Purchase",
            'action_source' => 'business_messaging',
            'messaging_channel' => 'whatsapp',
            'user_data' => ['whatsapp_business_account_id' => 'WABA_ID', 'ctwa_clid' => 'ARAkLkA8rmlF'],
            'custom_data' => ['currency' => 'INR', 'value' => 250000.0],
        ], $this->received[2]);
        $this->assertStringNotContainsString('9876543210', json_encode($this->received), 'no phone numbers');
        $this->assertStringNotContainsString('Priya', json_encode($this->received), 'no names');

        $this->assertSame(3, AdConversion::where('status', 'sent')->count());
        $this->get('/settings/integrations/whatsapp')->assertSee('Ad results for Meta')->assertSeeInOrder(['New ad leads reported', '1', 'Qualified leads reported', '1', 'Customers reported', '1']);
    }

    public function test_only_click_to_whatsapp_leads_are_reported_and_only_while_it_is_on(): void
    {
        $this->turnOn();
        $organic = Lead::factory()->for($this->admin->organization)->create(['click_id' => 'gclid-1', 'click_type' => 'gclid']);
        $this->move($organic, 'Won');

        $this->put('/settings/integrations/whatsapp/conversions', ['qualified_status_id' => '']); // switched off
        $this->adLead();

        $this->assertSame([], $this->received);
    }

    public function test_refusals_are_shown_and_setup_explains_the_missing_permission(): void
    {
        $this->metaRefuses = true;

        $this->actingAs($this->admin)->post('/settings/integrations/whatsapp/conversions')
            ->assertSessionHasErrors(['conversions' => 'Meta said: (#200) Permissions error The access token needs the whatsapp_business_manage_events permission.']);

        $this->whatsapp->forceFill(['settings' => $this->whatsapp->settings + ['dataset_id' => 'DATASET_1', 'conversions_on' => true]])->save();
        $this->adLead();

        $this->assertSame('The ctwa_clid is not valid.', AdConversion::sole()->error);
        $this->actingAs($this->admin)->get('/settings/integrations/whatsapp')->assertSee('Last problem: The ctwa_clid is not valid.');
    }
}
