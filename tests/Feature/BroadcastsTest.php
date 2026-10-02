<?php

namespace Tests\Feature;

use App\Broadcasts\BroadcastAudience;
use App\Models\Broadcast;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BroadcastsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Integration $whatsapp;

    private WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.Str::random(12)]]])]);
        $this->admin = $this->registerOrganization('Sunrise Realty');
        $this->whatsapp = $this->connectWhatsApp($this->admin->organization);
        $this->template = WhatsAppTemplate::query()->make(['name' => 'diwali_offer', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, {{2}} has a Diwali offer!', 'variables' => 2]);
        $this->template->organization_id = $this->admin->organization_id;
        $this->template->save();
    }

    private function lead(array $attributes = []): Lead
    {
        static $phone = 9800000100;

        return Lead::factory()->for($this->admin->organization)->create($attributes + ['phone' => '+91'.$phone++, 'created_by' => $this->admin->id]);
    }

    private function send(array $data): TestResponse
    {
        return $this->actingAs($this->admin)->post('/broadcasts', $data + [
            'name' => 'Diwali offer', 'whatsapp_template_id' => $this->template->id, 'audience' => ['stage' => 'open'], 'confirm' => '1',
        ]);
    }

    public function test_the_summary_counts_who_will_get_it_and_what_meta_charges(): void
    {
        $this->lead(['name' => 'Priya Sharma']);
        $this->lead(['name' => 'Ravi Kumar']);
        $this->lead(['opted_out_at' => now()]);
        $this->lead(['status_id' => LeadStatus::query()->where('name', 'Won')->sole()->id]);

        $this->actingAs($this->admin)->post('/broadcasts', ['preview' => '1', 'whatsapp_template_id' => $this->template->id, 'audience' => ['stage' => 'open']])
            ->assertRedirect('/broadcasts/create');
        $this->get('/broadcasts/create')->assertOk()
            ->assertSeeInOrder(['2', 'leads will get it', '1 who said STOP are left out'])
            ->assertSee('Meta charges about <b>₹2</b>', false)
            ->assertSee('Hi {{1}}, {{2}} has a Diwali offer!')
            ->assertSee('Send to 2 leads');
    }

    public function test_each_lead_gets_their_own_message_and_results_are_tracked(): void
    {
        $priya = $this->lead(['name' => 'Priya Sharma']);
        $this->lead(['name' => 'Ravi Kumar']);
        $this->lead(['name' => 'Stopped', 'opted_out_at' => now()]);

        $this->send(['confirm' => ''])->assertSessionHasErrors(['confirm' => 'Tick the box to confirm you want to send this.']);
        $this->send(['values' => [2 => 'Sunrise Homes']])->assertRedirect('/broadcasts/'.Broadcast::sole()->id);

        $broadcast = Broadcast::sole();
        $this->assertSame([Broadcast::DONE, 2, 0], [$broadcast->status, $broadcast->total, $broadcast->skipped]);
        $this->assertSame(['Hi Priya, Sunrise Homes has a Diwali offer!', 'Hi Ravi, Sunrise Homes has a Diwali offer!'],
            WhatsAppMessage::query()->where('broadcast_id', $broadcast->id)->orderBy('id')->pluck('body')->all());
        $this->assertNull($priya->fresh()->first_contacted_at, 'a broadcast is not a personal reply');

        $this->whatsAppWebhook($this->whatsapp, [['from' => ltrim($priya->phone, '+'), 'id' => 'wamid.reply', 'type' => 'text', 'text' => ['body' => 'Interested!']]]);
        $this->actingAs($this->admin)->get("/broadcasts/{$broadcast->id}")->assertOk()
            ->assertSeeInOrder(['Sent', '2', 'Delivered', 'Read', 'Replied', '1'])
            ->assertSee('Priya Sharma');
        $this->get('/broadcasts')->assertSee('Diwali offer')->assertSee('Open leads');
        $this->get("/leads/{$priya->id}?tab=whatsapp")->assertSee('Broadcast ·');
    }

    public function test_the_audience_can_be_narrowed_by_stage_source_campaign_and_owner(): void
    {
        $agent = $this->addAgent($this->admin->organization, ['name' => 'Asha']);
        $this->actingAs($this->admin);
        $facebook = Source::query()->create(['name' => 'Facebook Ads']);
        $won = LeadStatus::query()->where('name', 'Won')->sole();
        $this->lead(['name' => 'Fb Open', 'source_id' => $facebook->id, 'campaign' => 'Diwali', 'assigned_to' => $agent->id]);
        $this->lead(['name' => 'Fb Other Campaign', 'source_id' => $facebook->id, 'campaign' => 'Monsoon', 'assigned_to' => $agent->id]);
        $this->lead(['name' => 'Fb Customer', 'source_id' => $facebook->id, 'status_id' => $won->id]);
        $this->lead(['name' => 'Walk-in Open']);

        $this->send(['audience' => ['stage' => 'open', 'source_id' => $facebook->id, 'campaign' => 'Diwali', 'assigned_to' => $agent->id]]);
        $this->send(['audience' => ['stage' => 'won']]);

        $this->assertSame(['Fb Open', 'Fb Customer'], WhatsAppMessage::query()->with('lead')->orderBy('id')->get()->pluck('lead.name')->all());
        $this->assertSame('Open leads · from Facebook Ads · campaign “Diwali” · owned by Asha', app(BroadcastAudience::class)->describe(Broadcast::query()->oldest('id')->first()->audience));
    }

    public function test_only_admins_with_whatsapp_can_send_broadcasts(): void
    {
        $agent = $this->addAgent($this->admin->organization);
        $this->actingAs($agent)->get('/broadcasts')->assertForbidden();
        $this->post('/broadcasts', [])->assertForbidden();

        $this->actingAs($this->admin)->get('/dashboard')->assertSee(route('broadcasts.index'), false);
        $this->admin->organization->forceFill(['plan' => 'starter', 'subscription_status' => 'active'])->save();
        $this->actingAs($this->admin->fresh())->get('/broadcasts')->assertRedirect('/settings/billing');
    }
}
