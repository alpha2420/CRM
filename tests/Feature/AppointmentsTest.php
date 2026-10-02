<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\IntegrationType;
use App\Models\Appointment;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-01-08 10:00', 'Asia/Kolkata'));
        $this->admin = $this->registerOrganization('Sunrise Realty');
        $this->agent = $this->addAgent($this->admin->organization, ['name' => 'Asha']);
        $this->lead = Lead::factory()->for($this->admin->organization)->create(['name' => 'Priya Sharma', 'assigned_to' => $this->agent->id, 'next_follow_up_at' => null]);
    }

    private function book(string $when = '2030-01-09T11:00', array $extra = []): Appointment
    {
        $this->actingAs($this->agent)->post("/leads/{$this->lead->id}/appointments", $extra + ['type' => 'site_visit', 'starts_at' => $when, 'location' => 'Baner show flat']);

        return Appointment::query()->latest('id')->firstOrFail();
    }

    public function test_booking_a_meeting_makes_it_the_next_step_and_notes_it(): void
    {
        $this->actingAs($this->agent)->post("/leads/{$this->lead->id}/appointments", ['type' => 'site_visit', 'starts_at' => '2030-01-07T11:00'])
            ->assertSessionHasErrors('starts_at');

        $appointment = $this->book();

        $this->assertTrue($appointment->starts_at->equalTo(Carbon::parse('2030-01-09 11:00', 'Asia/Kolkata')), 'typed in local time');
        $this->assertSame($this->agent->id, $appointment->user_id);
        $this->assertTrue($this->lead->fresh()->next_follow_up_at->equalTo($appointment->starts_at));
        $this->assertSame('Booked a site visit on Wed 9 Jan, 11:00 at Baner show flat.', $this->lead->activities()->sole()->note);
        $this->get("/leads/{$this->lead->id}")->assertSee('Site visit')->assertSee('Cancel meeting');

        $other = Lead::factory()->for($this->admin->organization)->create(['assigned_to' => $this->admin->id]);
        $this->post("/leads/{$other->id}/appointments", ['type' => 'call', 'starts_at' => '2030-01-09T11:00'])->assertForbidden();
    }

    public function test_the_lead_and_owner_are_reminded_once_each(): void
    {
        Notification::fake();
        $whatsapp = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => ['phone_number_id' => 'P', 'waba_id' => 'W', 'access_token' => 't', 'app_secret' => 's', 'default_country_code' => '91']]);
        $whatsapp->organization_id = $this->admin->organization_id;
        $whatsapp->save();
        $template = WhatsAppTemplate::query()->make(['name' => 'visit_reminder', 'language' => 'en', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, see you {{2}} at {{3}}.', 'variables' => 3]);
        $template->organization_id = $this->admin->organization_id;
        $template->save();
        $this->admin->organization->forceFill(['autopilot' => ['meeting_template_id' => $template->id]])->save();
        $this->book('2030-01-09T11:00');

        $this->artisan('crm:appointment-reminders')->expectsOutput('Sent 0 meeting reminders.'); // 25 hours away

        $this->travelTo(Carbon::parse('2030-01-08 11:30', 'Asia/Kolkata'));
        $this->artisan('crm:appointment-reminders')->expectsOutput('Sent 1 meeting reminders.');
        $this->artisan('crm:appointment-reminders')->expectsOutput('Sent 0 meeting reminders.');
        $this->assertSame('Hi Priya, see you Wed 9 Jan, 11:00 at Baner show flat.', WhatsAppMessage::sole()->body);

        $this->travelTo(Carbon::parse('2030-01-09 10:05', 'Asia/Kolkata'));
        $this->artisan('crm:appointment-reminders')->expectsOutput('Sent 2 meeting reminders.'); // lead + owner
        $this->artisan('crm:appointment-reminders')->expectsOutput('Sent 0 meeting reminders.');
        Notification::assertSentToTimes($this->agent, AppointmentReminderNotification::class, 1);
        $this->assertSame(2, WhatsAppMessage::count());
    }

    public function test_a_cancelled_meeting_sends_no_reminders_and_outcomes_are_recorded(): void
    {
        Notification::fake();
        $cancelled = $this->book('2030-01-08T10:30');
        $this->patch("/appointments/{$cancelled->id}", ['outcome' => 'cancelled']);
        $this->artisan('crm:appointment-reminders')->expectsOutput('Sent 0 meeting reminders.');

        $missed = $this->book('2030-01-08T10:40');
        $this->travel(2)->hours();
        $this->get("/leads/{$this->lead->id}")->assertSee('How did it go?');
        $this->patch("/appointments/{$missed->id}", ['outcome' => 'no_show'])->assertSessionHas('status', 'Meeting updated.');

        $this->assertSame(AppointmentStatus::NoShow, $missed->fresh()->status);
        $this->assertTrue($this->lead->fresh()->next_follow_up_at->lte(now()), 'due now, to reschedule');
        $this->patch("/appointments/{$missed->id}", ['outcome' => 'done'])->assertStatus(422);
    }

    public function test_the_dashboard_lists_todays_meetings_for_the_right_people(): void
    {
        $this->book('2030-01-08T15:00');
        $this->book('2030-01-10T15:00', ['type' => 'demo']);
        $outsider = $this->addAgent($this->admin->organization);

        $this->actingAs($this->agent)->get('/dashboard')->assertSee('Meetings today')->assertSee('15:00');
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Meetings today');
        $this->actingAs($outsider)->get('/dashboard')->assertDontSee('Meetings today');
    }
}
