<?php

namespace Tests\Feature;

use App\Ai\InsightGenerator;
use App\Ai\LeadInsight;
use App\Enums\IntegrationType;
use App\Enums\Priority;
use App\Integrations\WhatsAppService;
use App\Jobs\AnalyseLead;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Notifications\AutomationAlertNotification;
use App\Notifications\DailyDigestNotification;
use App\Notifications\LeadsHandedOverNotification;
use App\Notifications\WeeklyReportNotification;
use App\Services\LeadIntake;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutopilotTest extends TestCase
{
    use RefreshDatabase;

    /** 12:00 on a Tuesday in India: inside the default working hours. */
    private function workingHours(string $time = '2030-01-08 12:00'): void
    {
        $this->travelTo(Carbon::parse($time, 'Asia/Kolkata'));
    }

    private function stage(Organization $organization, string $name): LeadStatus
    {
        return $organization->leadStatuses()->where('name', $name)->sole();
    }

    private function autopilot(Organization $organization, array $changes): void
    {
        $organization->forceFill(['autopilot' => $changes + $organization->autopilot()->toArray()])->save();
    }

    private function connectWhatsApp(Organization $organization): Integration
    {
        $integration = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => [
            'phone_number_id' => 'PHONE_ID', 'waba_id' => 'WABA_ID', 'access_token' => 'token',
            'app_secret' => 'app-secret', 'verify_token' => 'verify-me', 'default_country_code' => '91',
        ]]);
        $integration->organization_id = $organization->id;
        $integration->save();

        return $integration;
    }

    private function inbound(Integration $integration, string $from, string $text, string $id): void
    {
        app(WhatsAppService::class)->handleWebhook($integration, ['entry' => [['changes' => [['field' => 'messages', 'value' => [
            'messages' => [['from' => $from, 'id' => $id, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]]);
    }

    private function capture(Organization $organization, string $phone = '+919800000001', string $name = 'Kiran Rao'): Lead
    {
        return app(LeadIntake::class)->capture($organization, ['name' => $name, 'phone' => $phone], 'Website')->lead;
    }

    // ---- New leads ---------------------------------------------------------

    public function test_new_leads_get_a_first_follow_up_but_imports_do_not(): void
    {
        $this->workingHours();
        $admin = $this->registerOrganization();
        $organization = $admin->organization;

        $this->actingAs($admin)->post('/leads', ['name' => 'Walk In', 'phone' => '+919811111111', 'priority' => 'medium'])->assertRedirect();
        $this->assertTrue(Lead::where('name', 'Walk In')->sole()->next_follow_up_at->equalTo(now()->addMinutes(15)));

        $imported = app(LeadService::class)->create($organization, ['name' => 'Old Contact', 'phone' => '+919822222222'], $admin, planFirstCall: false);
        $this->assertNull($imported->next_follow_up_at);

        $this->autopilot($organization, ['first_follow_up' => false]);
        $this->assertNull($this->capture($organization->fresh())->next_follow_up_at);
    }

    public function test_unanswered_leads_are_passed_on_once_and_admins_are_told(): void
    {
        Notification::fake();
        $this->workingHours();
        $admin = $this->registerOrganization();
        $asha = $this->addAgent($admin->organization, ['name' => 'Asha']);
        $ravi = $this->addAgent($admin->organization, ['name' => 'Ravi']);
        $slow = $this->capture($admin->organization);
        $answered = $this->capture($admin->organization, '+919800000002', 'Called Back');
        $answered->forceFill(['first_contacted_at' => now()])->save();
        $this->assertSame($asha->id, $slow->assigned_to);

        $this->travel(31)->minutes();
        $this->artisan('crm:autopilot')->assertSuccessful();

        $slow->refresh();
        $this->assertSame($ravi->id, $slow->assigned_to);
        $this->assertNotNull($slow->escalated_at);
        $this->assertStringContainsString('passed from Asha to Ravi', $slow->activities()->sole()->note);
        $this->assertSame($ravi->id, $answered->fresh()->assigned_to);
        Notification::assertSentTo($admin, AutomationAlertNotification::class);

        $this->travel(31)->minutes();
        $this->artisan('crm:autopilot');
        $this->assertSame($ravi->id, $slow->fresh()->assigned_to);
    }

    public function test_nothing_is_passed_on_at_night_and_overnight_leads_get_a_grace_period(): void
    {
        $this->workingHours('2030-01-08 23:00');
        $admin = $this->registerOrganization();
        $asha = $this->addAgent($admin->organization);
        $this->addAgent($admin->organization);
        $lead = $this->capture($admin->organization);

        $this->travelTo(Carbon::parse('2030-01-09 10:15', 'Asia/Kolkata'));
        $this->artisan('crm:autopilot');
        $this->assertSame($asha->id, $lead->fresh()->assigned_to);

        $this->travelTo(Carbon::parse('2030-01-09 10:31', 'Asia/Kolkata'));
        $this->artisan('crm:autopilot');
        $this->assertNotSame($asha->id, $lead->fresh()->assigned_to);
    }

    // ---- Follow-ups --------------------------------------------------------

    public function test_a_follow_up_saved_without_a_date_plans_the_next_one(): void
    {
        $this->workingHours();
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();
        $contacted = $this->stage($admin->organization, 'Contacted');

        $this->actingAs($admin)->post("/leads/{$lead->id}/activities", ['status_id' => $contacted->id, 'note' => 'Called']);
        $this->assertTrue($lead->fresh()->next_follow_up_at->equalTo(Carbon::parse('2030-01-10 11:00', 'Asia/Kolkata')));

        $this->post("/leads/{$lead->id}/activities", ['status_id' => $this->stage($admin->organization, 'Won')->id]);
        $this->assertNull($lead->fresh()->next_follow_up_at);
    }

    public function test_the_first_whatsapp_message_from_a_person_marks_the_lead_contacted(): void
    {
        Queue::fake();
        $admin = $this->registerOrganization();
        $this->connectWhatsApp($admin->organization);
        $byPerson = Lead::factory()->for($admin->organization)->create(['last_inbound_at' => now()]);
        $byRobot = Lead::factory()->for($admin->organization)->create(['last_inbound_at' => now()]);

        $this->actingAs($admin)->post("/leads/{$byPerson->id}/whatsapp", ['body' => 'Hi! Thanks for asking.']);
        app(WhatsAppService::class)->sendText($byRobot->fresh(), null, 'Automatic hello');

        $this->assertSame('Contacted', $byPerson->fresh()->status->name);
        $this->assertSame('New', $byRobot->fresh()->status->name);
        $this->assertNull($byRobot->fresh()->first_contacted_at);
    }

    public function test_a_lost_lead_that_comes_back_is_reopened_and_its_owner_alerted(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $lost = Lead::factory()->for($admin->organization)->create([
            'phone' => '+919800000001', 'assigned_to' => $agent->id, 'status_id' => $this->stage($admin->organization, 'Lost')->id,
        ]);

        $this->capture($admin->organization, '+919800000001');

        $lost->refresh();
        $this->assertSame('New', $lost->status->name);
        $this->assertTrue($lost->next_follow_up_at->lte(now()));
        Notification::assertSentTo($agent, AutomationAlertNotification::class);

        // The same happens when they write on WhatsApp.
        $lost->update(['status_id' => $this->stage($admin->organization, 'Lost')->id]);
        $this->inbound($this->connectWhatsApp($admin->organization), '919800000001', 'Hello again', 'wamid.back');
        $this->assertSame('New', $lost->fresh()->status->name);
    }

    // ---- Quiet leads -------------------------------------------------------

    public function test_quiet_leads_are_nudged_once_with_the_chosen_template(): void
    {
        Queue::fake();
        $this->workingHours();
        $admin = $this->registerOrganization();
        $this->connectWhatsApp($admin->organization);
        $template = new WhatsAppTemplate(['name' => 'checking_in', 'language' => 'en', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, still interested?', 'variables' => 1]);
        $template->organization_id = $admin->organization_id;
        $template->save();
        $this->autopilot($admin->organization, ['reengage' => true, 'reengage_template_id' => $template->id]);
        $quiet = Lead::factory()->for($admin->organization)->create(['name' => 'Meera Joshi', 'created_at' => now()->subDays(15)]);
        $fresh = Lead::factory()->for($admin->organization)->create(['created_at' => now()->subDays(3)]);

        $this->artisan('crm:autopilot');
        $this->artisan('crm:autopilot');

        $message = WhatsAppMessage::sole();
        $this->assertSame($quiet->id, $message->lead_id);
        $this->assertSame('Hi Meera, still interested?', $message->body);
        $this->assertNotNull($quiet->fresh()->reengaged_at);
        $this->assertTrue($quiet->fresh()->next_follow_up_at->lte(now()));
        $this->assertNull($fresh->fresh()->reengaged_at);
    }

    public function test_dead_leads_are_closed_only_when_switched_on(): void
    {
        $this->workingHours();
        $admin = $this->registerOrganization();
        $dead = Lead::factory()->for($admin->organization)->create(['created_at' => now()->subDays(61)]);

        $this->artisan('crm:autopilot');
        $this->assertSame('New', $dead->fresh()->status->name);

        $this->autopilot($admin->organization, ['auto_close' => true]);
        $this->artisan('crm:autopilot');
        $this->assertSame('Lost', $dead->fresh()->status->name);
        $this->assertNotNull($dead->fresh()->closed_at);
    }

    // ---- WhatsApp and AI ---------------------------------------------------

    public function test_the_away_message_goes_out_once_outside_working_hours(): void
    {
        Queue::fake();
        $this->workingHours('2030-01-08 22:00');
        $admin = $this->registerOrganization();
        $whatsapp = $this->connectWhatsApp($admin->organization);
        $this->autopilot($admin->organization, ['away_message' => true, 'away_text' => 'We are closed, back at 10!']);

        $this->inbound($whatsapp, '919800000001', 'Hi', 'wamid.1');
        $this->inbound($whatsapp, '919800000001', 'Anyone there?', 'wamid.2');
        $this->assertSame(['We are closed, back at 10!'], WhatsAppMessage::where('direction', 'out')->pluck('body')->all());

        $this->workingHours('2030-01-09 12:00');
        $this->inbound($whatsapp, '919800000002', 'Hello', 'wamid.3');
        $this->assertSame(1, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_ai_follow_through_refreshes_the_summary_and_flags_hot_leads(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        $this->app->instance(InsightGenerator::class, new class implements InsightGenerator
        {
            public function generate(string $system, string $prompt): LeadInsight
            {
                $insight = new LeadInsight;
                $insight->summary = 'Ready to buy.';
                $insight->temperature = 'hot';
                $insight->reason = 'Asked for the invoice.';
                $insight->next_step = 'Call now.';
                $insight->suggested_message = 'Sending it now.';

                return $insight;
            }
        });
        Queue::fake();
        $admin = $this->registerOrganization();
        $whatsapp = $this->connectWhatsApp($admin->organization);
        $this->autopilot($admin->organization, ['ai_on_reply' => true]);
        $lead = Lead::factory()->for($admin->organization)->create(['phone' => '+919800000001', 'priority' => Priority::Low]);

        $this->inbound($whatsapp, '919800000001', 'Please send the invoice', 'wamid.ai');
        Queue::assertPushed(AnalyseLead::class, fn (AnalyseLead $job) => $job->leadId === $lead->id);

        app()->call([new AnalyseLead($lead->id), 'handle']);
        $this->assertSame(Priority::High, $lead->fresh()->priority);
        $this->assertSame('Ready to buy.', $lead->fresh()->ai_insight['summary']);
    }

    // ---- Team --------------------------------------------------------------

    public function test_open_leads_of_a_deactivated_agent_are_shared_with_the_team(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $leaving = $this->addAgent($admin->organization, ['name' => 'Leaving Agent']);
        $asha = $this->addAgent($admin->organization);
        $ravi = $this->addAgent($admin->organization);
        Lead::factory()->count(4)->for($admin->organization)->create(['assigned_to' => $leaving->id]);
        $won = Lead::factory()->for($admin->organization)->create(['assigned_to' => $leaving->id, 'status_id' => $this->stage($admin->organization, 'Won')->id]);

        $this->actingAs($admin)->put("/users/{$leaving->id}", ['name' => $leaving->name, 'email' => $leaving->email, 'role' => 'agent', 'is_active' => 0])
            ->assertSessionHas('status', 'User updated. 4 open leads shared with the team.');

        $this->assertSame(2, Lead::where('assigned_to', $asha->id)->count());
        $this->assertSame(2, Lead::where('assigned_to', $ravi->id)->count());
        $this->assertSame($leaving->id, $won->fresh()->assigned_to);
        Notification::assertSentTo([$asha, $ravi], LeadsHandedOverNotification::class);
    }

    public function test_morning_summaries_go_out_at_nine_local_time_once_and_reports_on_mondays(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2030-01-07 08:30', 'Asia/Kolkata')); // a Monday
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $idle = $this->addAgent($admin->organization);
        Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id, 'next_follow_up_at' => now()->subDay()]);

        $this->artisan('crm:digests');
        Notification::assertNotSentTo([$agent, $admin], DailyDigestNotification::class);

        $this->travelTo(Carbon::parse('2030-01-07 09:00', 'Asia/Kolkata'));
        $this->artisan('crm:digests');
        $this->travelTo(Carbon::parse('2030-01-07 09:30', 'Asia/Kolkata'));
        $this->artisan('crm:digests');

        Notification::assertSentToTimes($agent, DailyDigestNotification::class, 1);
        Notification::assertSentToTimes($admin, DailyDigestNotification::class, 1);
        Notification::assertNotSentTo($idle, DailyDigestNotification::class);
        Notification::assertSentToTimes($admin, WeeklyReportNotification::class, 1);
        Notification::assertNotSentTo($agent, WeeklyReportNotification::class);

        Notification::assertSentTo($admin, DailyDigestNotification::class, function (DailyDigestNotification $n) use ($admin, $agent) {
            $mail = implode("\n", $n->toMail($admin)->introLines);

            return str_contains($mail, "Overdue follow-ups in the team: {$agent->name} 1");
        });
    }

    // ---- Settings and housekeeping ------------------------------------------

    public function test_admins_change_autopilot_and_agents_cannot(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $form = ['first_follow_up_minutes' => 10, 'speed_to_lead_minutes' => 20, 'next_follow_up_days' => 3, 'reengage_days' => 10,
            'auto_close_days' => 90, 'away_text' => 'Back soon', 'work_start' => 9, 'work_end' => 18, 'first_follow_up' => 1, 'auto_close' => 1];

        $this->actingAs($admin)->get('/settings/autopilot')->assertOk()->assertSee('Pass on unanswered leads')->assertSee('9 of 13 on');
        $this->put('/settings/autopilot', $form)->assertSessionHasNoErrors()->assertSessionHas('status', 'Autopilot saved.');

        $settings = $admin->organization->fresh()->autopilot();
        $this->assertTrue($settings->on('auto_close'));
        $this->assertFalse($settings->on('speed_to_lead'));
        $this->assertSame(20, $settings->number('speed_to_lead_minutes'));

        $this->put('/settings/autopilot', ['work_start' => 18, 'work_end' => 9] + $form)->assertSessionHasErrors('work_end');
        $this->actingAs($agent)->get('/settings/autopilot')->assertForbidden();
    }

    public function test_whatsapp_templates_sync_every_night(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['name' => 'welcome', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']]],
        ]])]);
        $admin = $this->registerOrganization();
        $this->connectWhatsApp($admin->organization);

        $this->artisan('crm:sync-templates')->expectsOutput('Synced templates for 1 workspaces.')->assertSuccessful();
        $this->assertTrue(WhatsAppTemplate::withoutGlobalScopes()->where('name', 'welcome')->sole()->isApproved());
    }
}
