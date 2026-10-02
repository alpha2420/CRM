<?php

namespace Tests\Feature;

use App\Autopilot\Digests;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use App\Services\LeadIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-03-12 11:00', 'Asia/Kolkata')); // a Tuesday
        $this->admin = $this->registerOrganization();
        $this->agent = $this->addAgent($this->admin->organization, ['name' => 'Asha']);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::factory()->for($this->admin->organization)->create($attributes + ['assigned_to' => $this->agent->id, 'next_follow_up_at' => null, 'created_by' => $this->admin->id]);
    }

    public function test_a_to_do_on_a_lead_can_be_added_and_ticked_off(): void
    {
        $lead = $this->lead(['name' => 'Kiran Rao']);

        $this->actingAs($this->agent)->post('/tasks', ['title' => 'Send the brochure', 'due' => 'tomorrow', 'lead_id' => $lead->id])->assertSessionHas('status', 'To-do added.');
        $task = Task::sole();
        $this->assertSame([$this->agent->id, $lead->id], [$task->user_id, $task->lead_id]);
        $this->assertSame('2030-03-13 10:00', $task->due_at->local()->format('Y-m-d H:i'));
        $this->get("/leads/{$lead->id}")->assertSee('To-dos')->assertSee('Send the brochure')->assertSee('Add a to-do for Kiran');

        $this->patch("/tasks/{$task->id}", ['done' => 1])->assertSessionHas('status', "Nice, that's done.");
        $this->assertNotNull($task->fresh()->done_at);
        $this->assertSame('Done: Send the brochure', $lead->activities()->latest('id')->first()->note);
    }

    public function test_people_only_manage_their_own_to_dos_and_leads(): void
    {
        $ravi = $this->addAgent($this->admin->organization);
        $theirs = $this->lead(['assigned_to' => $ravi->id]);

        $this->actingAs($this->agent)->post('/tasks', ['title' => 'Snoop', 'lead_id' => $theirs->id])->assertSessionHasErrors('lead_id');
        $this->post('/tasks', ['title' => 'For Ravi?', 'user_id' => $ravi->id, 'due' => '']);
        $this->assertSame($this->agent->id, Task::sole()->user_id, 'agents set to-dos for themselves');

        $this->actingAs($ravi)->patch('/tasks/'.Task::sole()->id, ['done' => 1])->assertForbidden();
        $this->delete('/tasks/'.Task::sole()->id)->assertForbidden();

        $this->actingAs($this->admin)->post('/tasks', ['title' => 'Call the bank', 'user_id' => $ravi->id, 'due' => 'today']);
        $this->assertSame($ravi->id, Task::query()->where('title', 'Call the bank')->sole()->user_id, 'admins can set them for others');
        $this->assertSame('18:00', Task::query()->where('title', 'Call the bank')->sole()->due_at->local()->format('H:i'));
    }

    public function test_picking_a_time_needs_a_time(): void
    {
        $this->actingAs($this->agent)->post('/tasks', ['title' => 'X', 'due' => 'custom'])->assertSessionHasErrors(['due_at' => 'Pick a date and time, or choose Today or Tomorrow.']);
        $this->post('/tasks', ['title' => 'X', 'due' => 'custom', 'due_at' => '2030-03-20T15:30']);
        $this->assertSame('2030-03-20 15:30', Task::sole()->due_at->local()->format('Y-m-d H:i'));
    }

    public function test_my_day_brings_everything_for_today_together_in_order(): void
    {
        $this->lead(['name' => 'Overdue Ola', 'next_follow_up_at' => now()->subDay()]);
        $this->lead(['name' => 'Later Lata', 'next_follow_up_at' => now()->addHours(3)]);
        $this->lead(['name' => 'Next Week Nina', 'next_follow_up_at' => now()->addWeek()]);
        $meetingLead = $this->lead(['name' => 'Meeting Meera']);
        $meeting = new Appointment(['type' => 'site_visit', 'starts_at' => now()->addHour(), 'location' => 'Baner show flat']);
        $meeting->lead()->associate($meetingLead);
        $meeting->organization_id = $this->admin->organization_id;
        $meeting->user_id = $this->agent->id;
        $meeting->save();
        $this->actingAs($this->agent)->post('/tasks', ['title' => 'Renew the listing', 'due' => 'custom', 'due_at' => '2030-03-12T09:00']);
        $this->post('/tasks', ['title' => 'Order visiting cards', 'due' => '']);
        $waiting = app(LeadIntake::class)->capture($this->admin->organization, ['name' => 'Waiting Wasim', 'phone' => '+919800000099'], 'Website')->lead;
        $waiting->forceFill(['assigned_to' => $this->agent->id])->save();

        $this->get('/today')->assertOk()
            ->assertSeeInOrder(['Overdue', 'Overdue Ola', 'Renew the listing', 'Today', 'Site visit with Meeting Meera', 'Baner show flat', 'Later Lata', 'No date', 'Order visiting cards'])
            ->assertSee('New leads waiting')->assertSee('Waiting Wasim')
            ->assertDontSee('Next Week Nina')
            ->assertSee('How My day works');

        $this->get('/dashboard')->assertSee('My day')->assertSee('<span class="count hot">4</span>', false); // 3 follow-ups (one is Wasim's first call) + 1 dated to-do

        $this->patch('/tasks/'.Task::query()->where('title', 'Renew the listing')->sole()->id, ['done' => 1]);
        $this->get('/today')->assertSeeInOrder(['1', 'done today'])->assertDontSee('Renew the listing');
    }

    public function test_the_morning_summary_mentions_todays_to_dos(): void
    {
        $this->actingAs($this->agent)->post('/tasks', ['title' => 'Renew the listing', 'due' => 'today']);

        $digest = app(Digests::class)->dailyFor($this->agent);
        $mail = (new DailyDigestNotification($digest))->toMail($this->agent);

        $this->assertSame(1, $digest['tasks']);
        $this->assertContains('You also have 1 to-do for today.', $mail->introLines);
        $this->assertSame(route('today'), $mail->actionUrl);
    }
}
