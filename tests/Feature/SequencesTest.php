<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\IntegrationType;
use App\Integrations\WhatsAppService;
use App\Models\Automation;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Sequences\SequenceEnroller;
use App\Services\LeadIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SequencesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-01-08 12:00', 'Asia/Kolkata')); // a Tuesday, inside working hours
        $this->admin = $this->registerOrganization();

        $whatsapp = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => [
            'phone_number_id' => 'P', 'waba_id' => 'W', 'access_token' => 't', 'app_secret' => 's', 'default_country_code' => '91',
        ]]);
        $whatsapp->organization_id = $this->admin->organization_id;
        $whatsapp->save();

        $this->template = new WhatsAppTemplate(['name' => 'welcome', 'language' => 'en', 'status' => 'APPROVED', 'body' => 'Hi {{1}}!', 'variables' => 1]);
        $this->template->organization_id = $this->admin->organization_id;
        $this->template->save();
    }

    private function sequence(array $attributes = []): Sequence
    {
        $sequence = new Sequence($attributes + ['name' => 'Welcome series', 'is_active' => true, 'stop_on_reply' => true]);
        $sequence->organization_id = $this->admin->organization_id;
        $sequence->save();
        $sequence->replaceSteps([
            ['day' => 0, 'action' => 'whatsapp_template', 'whatsapp_template_id' => $this->template->id, 'note' => null],
            ['day' => 2, 'action' => 'remind_owner', 'whatsapp_template_id' => null, 'note' => 'Call to offer a site visit'],
        ]);

        return $sequence;
    }

    private function lead(Organization $organization, array $attributes = []): Lead
    {
        return Lead::factory()->for($organization)->create($attributes + ['name' => 'Meera Joshi', 'next_follow_up_at' => null]);
    }

    public function test_admins_build_a_sequence_and_the_form_checks_each_step(): void
    {
        $this->actingAs($this->admin)->get('/settings/sequences')->assertOk()->assertSee('No sequences yet');

        $this->post('/settings/sequences', ['name' => 'Empty'])->assertSessionHasErrors('steps');
        $this->post('/settings/sequences', ['name' => 'Bad', 'steps' => [['day' => 1, 'action' => 'whatsapp_template']]])
            ->assertSessionHasErrors('steps.0.whatsapp_template_id');

        $this->post('/settings/sequences', ['name' => 'Welcome series', 'stop_on_reply' => 1, 'steps' => [
            ['day' => 3, 'action' => 'remind_owner', 'note' => 'Offer a visit'],
            ['day' => 0, 'action' => 'whatsapp_template', 'whatsapp_template_id' => $this->template->id, 'note' => 'ignored'],
            ['day' => '', 'action' => ''],
        ]])->assertRedirect('/settings/sequences');

        $sequence = Sequence::sole();
        $this->assertSame([0, 3], $sequence->steps->pluck('day')->all(), 'sorted by day, blank rows dropped');
        $this->assertNull($sequence->steps[0]->note, 'only the field the action uses is kept');
        $this->get('/settings/sequences')->assertSee('Send “welcome” on WhatsApp')->assertSee('Remind owner: Offer a visit');

        $this->actingAs($this->addAgent($this->admin->organization))->get('/settings/sequences')->assertForbidden();
    }

    public function test_due_steps_run_inside_working_hours_and_the_last_completes_it(): void
    {
        $lead = $this->lead($this->admin->organization);
        app(SequenceEnroller::class)->enroll($lead, $this->sequence());

        $this->artisan('crm:sequences')->expectsOutput('Ran 1 sequence steps.');
        $this->assertSame('Hi Meera!', WhatsAppMessage::sole()->body);
        $enrollment = SequenceEnrollment::sole();
        $this->assertSame(1, $enrollment->next_step);
        $this->assertTrue($enrollment->next_run_at->equalTo(now()->addDays(2)));

        $this->travel(1)->days();
        $this->artisan('crm:sequences')->expectsOutput('Ran 0 sequence steps.');

        $this->travel(1)->days();
        $this->artisan('crm:sequences');
        $this->assertTrue($lead->fresh()->next_follow_up_at->lte(now()), 'the owner is reminded');
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->fresh()->status);
        $this->assertStringContainsString('step 2 of 2: reminded the owner: Call to offer a site visit', $lead->activities()->latest('id')->first()->note);
    }

    public function test_nothing_runs_at_night_or_while_a_sequence_is_paused(): void
    {
        $lead = $this->lead($this->admin->organization);
        $sequence = $this->sequence();
        app(SequenceEnroller::class)->enroll($lead, $sequence);

        $this->travelTo(Carbon::parse('2030-01-08 22:00', 'Asia/Kolkata'));
        $this->artisan('crm:sequences')->expectsOutput('Ran 0 sequence steps.');

        $this->travelTo(Carbon::parse('2030-01-09 12:00', 'Asia/Kolkata'));
        $sequence->update(['is_active' => false]);
        $this->artisan('crm:sequences')->expectsOutput('Ran 0 sequence steps.');

        $sequence->update(['is_active' => true]);
        $this->artisan('crm:sequences')->expectsOutput('Ran 1 sequence steps.');
    }

    public function test_a_reply_or_closing_the_lead_stops_the_sequence(): void
    {
        $replied = $this->lead($this->admin->organization, ['phone' => '+919800000001']);
        $won = $this->lead($this->admin->organization, ['phone' => '+919800000002']);
        $keepsGoing = $this->lead($this->admin->organization, ['phone' => '+919800000003']);
        app(SequenceEnroller::class)->enroll($replied, $sequence = $this->sequence());
        app(SequenceEnroller::class)->enroll($won, $sequence);
        app(SequenceEnroller::class)->enroll($keepsGoing, $this->sequence(['name' => 'Patient', 'stop_on_reply' => false]));

        $whatsapp = Integration::withoutGlobalScopes()->sole();
        foreach (['919800000001', '919800000003'] as $i => $from) {
            app(WhatsAppService::class)->handleWebhook($whatsapp, ['entry' => [['changes' => [['field' => 'messages', 'value' => [
                'messages' => [['from' => $from, 'id' => "wamid.{$i}", 'type' => 'text', 'text' => ['body' => 'Hi']]],
            ]]]]]]);
        }
        $this->actingAs($this->admin)->patch("/leads/{$won->id}/status", ['status_id' => $this->admin->organization->leadStatuses()->where('name', 'Won')->value('id')]);

        $this->assertSame('the lead replied', SequenceEnrollment::where('lead_id', $replied->id)->sole()->stop_reason);
        $this->assertSame('the lead was closed', SequenceEnrollment::where('lead_id', $won->id)->sole()->stop_reason);
        $this->assertSame(EnrollmentStatus::Active, SequenceEnrollment::where('lead_id', $keepsGoing->id)->sole()->status);
    }

    public function test_people_start_and_stop_sequences_from_the_lead_page_and_the_list(): void
    {
        $sequence = $this->sequence();
        $other = $this->sequence(['name' => 'Win-back']);
        $agent = $this->addAgent($this->admin->organization);
        $mine = $this->lead($this->admin->organization, ['assigned_to' => $agent->id]);
        $notMine = $this->lead($this->admin->organization, ['assigned_to' => $this->admin->id]);

        $this->actingAs($agent)->post("/leads/{$mine->id}/sequence", ['sequence_id' => $sequence->id])->assertSessionHas('status', 'Started “Welcome series”.');
        $this->get("/leads/{$mine->id}")->assertSee('Welcome series')->assertSee('Stop sequence');
        $this->post("/leads/{$notMine->id}/sequence", ['sequence_id' => $sequence->id])->assertForbidden();

        $this->post("/leads/{$mine->id}/sequence", ['sequence_id' => $other->id]);
        $this->assertSame('Replaced by “Win-back”', SequenceEnrollment::where('sequence_id', $sequence->id)->sole()->stop_reason, 'one sequence at a time');

        $this->delete("/leads/{$mine->id}/sequence")->assertSessionHas('status', 'Sequence stopped.');
        $this->assertSame(0, SequenceEnrollment::where('status', EnrollmentStatus::Active)->count());

        $this->actingAs($this->admin)->post('/leads/bulk', ['ids' => [$mine->id, $notMine->id], 'operation' => "sequence:{$sequence->id}"])
            ->assertSessionHas('status', '2 leads added to the sequence.');
        $this->assertSame(2, $sequence->activeEnrollments()->count());
    }

    public function test_an_automation_can_put_new_leads_into_a_sequence(): void
    {
        $sequence = $this->sequence();
        $this->actingAs($this->admin);
        Automation::create(['name' => 'Nurture new leads', 'trigger' => 'lead_created', 'conditions' => [], 'actions' => ['start_sequence_id' => $sequence->id], 'is_active' => true]);

        $lead = app(LeadIntake::class)->capture($this->admin->organization, ['name' => 'Kiran Rao', 'phone' => '+919811111111'], 'Website')->lead;

        $this->assertSame($sequence->id, $lead->activeEnrollment()->sole()->sequence_id);
        $this->actingAs($this->admin)->get('/settings/automations')->assertSee('start sequence');
    }
}
