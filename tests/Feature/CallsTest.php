<?php

namespace Tests\Feature;

use App\Calls\CallOutcome;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CallsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-03-12 11:00', 'Asia/Kolkata'));
        $this->admin = $this->registerOrganization();
        $this->agent = $this->addAgent($this->admin->organization, ['name' => 'Asha']);
        $this->lead = Lead::factory()->for($this->admin->organization)->create(['name' => 'Priya Sharma', 'assigned_to' => $this->agent->id, 'next_follow_up_at' => null]);
    }

    public function test_a_call_that_did_not_get_through_is_logged_in_one_tap_and_tried_again(): void
    {
        $this->actingAs($this->agent)->get("/leads/{$this->lead->id}")
            ->assertSee('data-call="'.route('leads.calls.store', $this->lead).'"', false)
            ->assertSee('How did the call with');

        $this->post("/leads/{$this->lead->id}/calls", ['outcome' => 'no_answer', 'seconds' => 40])
            ->assertSessionHas('status', 'Call logged: No answer. Next try: Today 13:00.');

        $activity = LeadActivity::sole();
        $this->assertSame(CallOutcome::NoAnswer, $activity->call_outcome);
        $this->assertSame(40, $activity->call_seconds);
        $this->assertSame('Call: No answer (40s).', $activity->note);
        $this->assertSame($this->lead->status_id, $activity->status_id, 'a call does not move the stage');
        $this->assertSame('13:00', $this->lead->fresh()->next_follow_up_at->local()->format('H:i'));
        $this->assertNotNull($this->lead->fresh()->first_contacted_at, 'trying counts as reaching out');

        $this->post("/leads/{$this->lead->id}/calls", ['outcome' => 'call_back', 'call_back' => 'tomorrow', 'note' => 'In a meeting']);
        $this->assertSame('2030-03-13 11:00', $this->lead->fresh()->next_follow_up_at->local()->format('Y-m-d H:i'));
        $this->assertSame('Call: Asked to call back. In a meeting.', LeadActivity::latest('id')->first()->note);

        $this->get("/leads/{$this->lead->id}")->assertSeeInOrder(['Asked to call back', 'No answer']);
    }

    public function test_after_talking_the_full_follow_up_form_records_the_call(): void
    {
        $interested = LeadStatus::query()->where('name', 'Interested')->sole();

        $this->actingAs($this->agent)->get("/leads/{$this->lead->id}?call=talked&seconds=130")
            ->assertSee('Logging your call (2m)')
            ->assertSee('name="call_seconds" value="130"', false);

        $this->post("/leads/{$this->lead->id}/activities", ['status_id' => $interested->id, 'note' => 'Wants a site visit', 'call_seconds' => 130]);

        $activity = LeadActivity::sole();
        $this->assertSame([CallOutcome::Connected, 130, 'Wants a site visit'], [$activity->call_outcome, $activity->call_seconds, $activity->note]);
        $this->assertSame('Interested', $this->lead->fresh()->status->name);

        $this->post("/leads/{$this->lead->id}/activities", ['status_id' => $interested->id, 'note' => 'No call this time']);
        $this->assertNull(LeadActivity::latest('id')->first()->call_outcome, 'a plain follow-up is not a call');
    }

    public function test_only_the_owner_or_an_admin_logs_calls_and_outcomes_are_checked(): void
    {
        $ravi = $this->addAgent($this->admin->organization);

        $this->actingAs($ravi)->post("/leads/{$this->lead->id}/calls", ['outcome' => 'busy'])->assertForbidden();
        $this->actingAs($this->agent)->post("/leads/{$this->lead->id}/calls", ['outcome' => 'teleported'])->assertSessionHasErrors('outcome');
        $this->actingAs($this->admin)->post("/leads/{$this->lead->id}/calls", ['outcome' => 'busy'])->assertSessionHasNoErrors();
    }

    public function test_reports_count_calls_and_how_many_got_through(): void
    {
        $this->actingAs($this->agent);
        foreach (['no_answer', 'busy', 'call_back', 'no_answer'] as $outcome) {
            $this->post("/leads/{$this->lead->id}/calls", ['outcome' => $outcome]);
        }

        $agentRow = collect($this->actingAs($this->admin)->get('/reports')->assertOk()->viewData('report')['agents'])->firstWhere('name', 'Asha');
        $this->assertSame([4, 25.0], [$agentRow['calls'], $agentRow['reached']]);
    }
}
