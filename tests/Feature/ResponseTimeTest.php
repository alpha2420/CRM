<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadIntake;
use App\Support\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResponseTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-03-12 11:00', 'Asia/Kolkata')); // a Tuesday; working hours 10:00–19:00
        $this->admin = $this->registerOrganization();
    }

    private function at(string $local): Carbon
    {
        return Carbon::parse($local, 'Asia/Kolkata');
    }

    private function arrives(string $phone, string $source = 'Website'): Lead
    {
        return app(LeadIntake::class)->capture($this->admin->organization, ['name' => "Lead {$phone}", 'phone' => $phone], $source)->lead;
    }

    public function test_only_working_hours_count(): void
    {
        $hours = WorkingHours::for($this->admin->organization);

        $this->assertSame(180, $hours->secondsBetween($this->at('2030-03-12 11:00'), $this->at('2030-03-12 11:03')));
        $this->assertSame(300, $hours->secondsBetween($this->at('2030-03-12 02:00'), $this->at('2030-03-12 10:05')), 'the night does not count');
        $this->assertSame(1200, $hours->secondsBetween($this->at('2030-03-16 18:50'), $this->at('2030-03-18 10:10')), 'Saturday evening to Monday morning, Sunday closed');
        $this->assertSame(0, $hours->secondsBetween($this->at('2030-03-12 11:03'), $this->at('2030-03-12 11:00')));
    }

    public function test_the_first_reply_time_is_recorded_for_leads_that_arrive_on_their_own(): void
    {
        $agent = $this->addAgent($this->admin->organization);
        $lead = $this->arrives('+919800000001');
        $manual = Lead::factory()->for($this->admin->organization)->create(['created_by' => $this->admin->id, 'assigned_to' => $agent->id]);

        $this->actingAs($this->admin)->get("/leads/{$lead->id}")->assertSee('Waiting 0s for a first reply');

        $this->travel(4)->minutes();
        $this->actingAs($agent)->post("/leads/{$lead->id}/activities", ['status_id' => $lead->status_id, 'note' => 'Called']);
        $this->post("/leads/{$manual->id}/activities", ['status_id' => $manual->status_id, 'note' => 'Called']);

        $this->assertSame(240, $lead->fresh()->response_seconds);
        $this->assertNull($manual->fresh()->response_seconds, 'a lead someone added by hand has already been spoken to');
        $this->get("/leads/{$lead->id}")->assertSee('First reply in 4m');

        $this->travel(1)->hour();
        $this->post("/leads/{$lead->id}/activities", ['status_id' => $lead->status_id, 'note' => 'Again']);
        $this->assertSame(240, $lead->fresh()->response_seconds, 'only the first reply counts');
    }

    public function test_the_dashboard_shows_speed_and_who_is_still_waiting(): void
    {
        $agent = $this->addAgent($this->admin->organization, ['name' => 'Asha']);
        $fast = $this->arrives('+919800000001');
        $fast->forceFill(['response_seconds' => 120, 'first_contacted_at' => now()])->save();
        $slow = $this->arrives('+919800000002');
        $slow->forceFill(['response_seconds' => 3600, 'first_contacted_at' => now()])->save();
        $this->travel(-20)->minutes();
        $waiting = $this->arrives('+919800000003', 'Facebook Ads');
        $this->travel(20)->minutes();

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Speed to lead')
            ->assertSeeInOrder(['33%', 'answered within 5 minutes', 'Median first reply 31m', '2 of 3 answered'])
            ->assertSeeInOrder(['Waiting for a first reply', $waiting->name, 'Facebook Ads', '20m']);

        $ravi = $this->addAgent($this->admin->organization, ['name' => 'Ravi']);
        $waiting->forceFill(['assigned_to' => $agent->id])->save();
        $this->actingAs($agent)->get('/dashboard')->assertSee($waiting->name);
        $this->actingAs($ravi)->get('/dashboard')->assertDontSee($waiting->name);
    }

    public function test_reports_show_speed_per_person(): void
    {
        $agent = $this->addAgent($this->admin->organization, ['name' => 'Asha']);
        foreach ([60, 200, 900] as $i => $seconds) {
            $lead = $this->arrives('+91980000001'.$i);
            $lead->forceFill(['assigned_to' => $agent->id, 'response_seconds' => $seconds, 'first_contacted_at' => now()])->save();
        }

        $this->actingAs($this->admin)->get('/reports')->assertOk()
            ->assertSeeInOrder(['Answered in 5 min', '67%', 'median first reply 3m'])
            ->assertSeeInOrder(['Asha', '3m', '67%']);
    }
}
