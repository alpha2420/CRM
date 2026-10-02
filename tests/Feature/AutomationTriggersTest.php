<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Enums\Priority;
use App\Integrations\WhatsAppService;
use App\Models\Automation;
use App\Models\CustomField;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Notifications\AutomationAlertNotification;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationTriggersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-01-08 12:00', 'Asia/Kolkata')); // inside working hours
        $this->admin = $this->registerOrganization();
        $this->actingAs($this->admin);
    }

    private function rule(string $trigger, array $conditions, array $actions, ?int $after = null): Automation
    {
        return Automation::create(['name' => 'Rule', 'trigger' => $trigger, 'trigger_after' => $after, 'conditions' => $conditions, 'actions' => $actions, 'is_active' => true]);
    }

    private function message(Integration $whatsapp, string $from, string $text): void
    {
        static $id = 0;
        app(WhatsAppService::class)->handleWebhook($whatsapp, ['entry' => [['changes' => [['field' => 'messages', 'value' => [
            'messages' => [['from' => $from, 'id' => 'wamid.'.++$id, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]]);
    }

    public function test_a_keyword_in_a_whatsapp_message_triggers_a_rule_at_most_once_a_day(): void
    {
        $whatsapp = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => ['phone_number_id' => 'P', 'waba_id' => 'W', 'access_token' => 't', 'app_secret' => 's', 'default_country_code' => '91']]);
        $whatsapp->organization_id = $this->admin->organization_id;
        $whatsapp->save();
        $template = WhatsAppTemplate::query()->make(['name' => 'price_list', 'language' => 'en', 'status' => 'APPROVED', 'body' => 'Our prices…', 'variables' => 0]);
        $template->organization_id = $this->admin->organization_id;
        $template->save();
        $this->rule('whatsapp_received', ['keywords' => 'price, cost'], ['whatsapp_template_id' => $template->id, 'set_priority' => 'high']);
        $lead = Lead::factory()->for($this->admin->organization)->create(['phone' => '+919800000001', 'priority' => Priority::Low]);

        $this->message($whatsapp, '919800000001', 'Hello there');
        $this->assertSame(0, WhatsAppMessage::where('direction', 'out')->count(), 'no keyword, no rule');

        $this->message($whatsapp, '919800000001', 'What is the PRICE for 3 users?');
        $this->message($whatsapp, '919800000001', 'And the cost of setup?');
        $this->assertSame(['Our prices…'], WhatsAppMessage::where('direction', 'out')->pluck('body')->all(), 'once a day per lead');
        $this->assertSame(Priority::High, $lead->fresh()->priority);

        $this->travel(25)->hours();
        $this->message($whatsapp, '919800000001', 'price again?');
        $this->assertSame(2, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_a_quiet_lead_rule_fires_once_per_quiet_spell(): void
    {
        Notification::fake();
        $this->rule('lead_quiet', [], ['notify_user_id' => $this->admin->id], after: 7);
        $quiet = Lead::factory()->for($this->admin->organization)->create(['created_at' => now()->subDays(8)]);
        Lead::factory()->for($this->admin->organization)->create(['created_at' => now()->subDays(3)]);

        $this->artisan('crm:automations')->expectsOutput('Fired 1 time-based rules.');
        $this->artisan('crm:automations')->expectsOutput('Fired 0 time-based rules.');

        $this->travel(1)->minutes();
        $this->post("/leads/{$quiet->id}/activities", ['status_id' => $quiet->status_id, 'note' => 'Called, no answer']);
        $this->travel(8)->days();
        $this->artisan('crm:automations')->expectsOutput('Fired 2 time-based rules.'); // a new quiet spell, plus the other lead
        Notification::assertSentTimes(AutomationAlertNotification::class, 3);
    }

    public function test_an_overdue_follow_up_rule_fires_once_per_follow_up_date_and_only_in_working_hours(): void
    {
        Notification::fake();
        $this->rule('follow_up_overdue', ['min_value' => 100000], ['notify_user_id' => $this->admin->id], after: 4);
        $big = Lead::factory()->for($this->admin->organization)->create(['value' => 250000, 'next_follow_up_at' => now()->subHours(5)]);
        Lead::factory()->for($this->admin->organization)->create(['value' => 5000, 'next_follow_up_at' => now()->subHours(5)]);
        Lead::factory()->for($this->admin->organization)->create(['value' => 250000, 'next_follow_up_at' => now()->subHours(2)]);

        $this->artisan('crm:automations')->expectsOutput('Fired 1 time-based rules.');
        $this->artisan('crm:automations')->expectsOutput('Fired 0 time-based rules.');

        $big->update(['next_follow_up_at' => now()->addHour()]);
        $this->travelTo(Carbon::parse('2030-01-08 22:00', 'Asia/Kolkata'));
        $this->artisan('crm:automations')->expectsOutput('Fired 0 time-based rules.');
        $this->travelTo(Carbon::parse('2030-01-09 11:00', 'Asia/Kolkata'));
        $this->artisan('crm:automations')->expectsOutput('Fired 2 time-based rules.'); // the moved date, and the other big lead
    }

    public function test_new_conditions_and_the_set_priority_action(): void
    {
        CustomField::create(['label' => 'Plan', 'key' => 'plan', 'type' => 'text', 'sort_order' => 1]);
        $this->rule('lead_created', ['priority' => 'medium', 'min_value' => 50000, 'city' => 'pune', 'field_key' => 'plan', 'field_value' => 'pro'], ['set_priority' => 'high']);
        $create = fn (array $data) => app(LeadService::class)->create($this->admin->organization, $data + [
            'name' => 'Lead', 'phone' => '+9198'.random_int(10000000, 99999999), 'priority' => 'medium', 'value' => 60000, 'city' => 'Pune', 'custom_values' => ['plan' => 'Pro'],
        ]);

        $this->assertSame(Priority::High, $create([])->fresh()->priority);
        $this->assertSame(Priority::Medium, $create(['value' => 10000])->fresh()->priority, 'value too small');
        $this->assertSame(Priority::Medium, $create(['city' => 'Mumbai'])->fresh()->priority, 'other city');
        $this->assertSame(Priority::Medium, $create(['custom_values' => ['plan' => 'Basic']])->fresh()->priority, 'other plan');
    }

    public function test_the_form_requires_a_wait_for_time_based_triggers_and_stores_typed_values(): void
    {
        $this->post('/settings/automations', ['name' => 'Quiet', 'trigger' => 'lead_quiet', 'actions' => ['notify_user_id' => $this->admin->id]])
            ->assertSessionHasErrors('trigger_after');

        $this->post('/settings/automations', [
            'name' => 'Price', 'trigger' => 'whatsapp_received', 'trigger_after' => 9,
            'conditions' => ['keywords' => ' price , cost ', 'min_value' => '1000', 'priority' => 'high'],
            'actions' => ['set_priority' => 'high', 'notify_user_id' => (string) $this->admin->id],
        ])->assertRedirect('/settings/automations');

        $rule = Automation::sole();
        $this->assertNull($rule->trigger_after, 'only time-based triggers keep a wait');
        $this->assertSame(['priority' => 'high', 'min_value' => 1000, 'keywords' => 'price , cost'], $rule->conditions);
        $this->assertSame(['set_priority' => 'high', 'notify_user_id' => $this->admin->id], $rule->actions);
        $this->get('/settings/automations')->assertSee('mentioning <b>price, cost</b>', false);
    }
}
