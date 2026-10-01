<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\WhatsAppMessage;
use App\Support\Avatar;
use App\Support\FollowUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAndUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page_with_live_plan_prices(): void
    {
        config(['plans.plans.growth.price' => 2799]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Never lose a lead again.')
            ->assertSee('₹2,799')
            ->assertSee('Start free trial');
    }

    public function test_signed_in_users_skip_the_landing_page(): void
    {
        $this->actingAs($this->registerOrganization())->get('/')->assertRedirect('/dashboard');
    }

    public function test_new_workspaces_get_a_getting_started_checklist_they_can_dismiss(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)->get('/dashboard')->assertSee('Get your workspace ready')->assertSee('0 of 5 done');

        Lead::factory()->for($admin->organization)->create();
        $this->get('/dashboard')->assertSee('1 of 5 done');

        $this->post('/onboarding/dismiss')->assertRedirect();
        $this->get('/dashboard')->assertDontSee('Get your workspace ready');
    }

    public function test_agents_never_see_the_checklist(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($agent)->get('/dashboard')->assertOk()->assertDontSee('Get your workspace ready');
        $this->post('/onboarding/dismiss')->assertForbidden();
    }

    public function test_opening_a_chat_in_the_inbox_marks_it_read_in_the_list(): void
    {
        $admin = $this->registerOrganization();
        $integration = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => ['phone_number_id' => 'P', 'waba_id' => 'W', 'access_token' => 't', 'app_secret' => 's']]);
        $integration->organization_id = $admin->organization_id;
        $integration->save();
        $lead = Lead::factory()->for($admin->organization)->create(['name' => 'Kiran Rao']);
        $lead->forceFill(['last_message_at' => now(), 'last_inbound_at' => now()])->save();
        $message = $lead->whatsappMessages()->make(['direction' => 'in', 'phone' => '91', 'body' => 'Hello there', 'status' => 'received']);
        $message->organization_id = $lead->organization_id;
        $message->save();

        $this->actingAs($admin)->get('/inbox')->assertSee('Kiran Rao')->assertSee('Select a conversation');

        $response = $this->get("/inbox?lead={$lead->id}")->assertOk()->assertSee('Hello there')->assertSee('Open lead');
        $this->assertSame(0, $response->viewData('conversations')->first()->unread_count);
        $this->assertNotNull(WhatsAppMessage::sole()->read_at);
    }

    public function test_follow_up_dates_read_naturally(): void
    {
        $this->travelTo(now()->setTime(10, 0));

        $this->assertSame(['text' => 'Today 11:00', 'tone' => 'today'], FollowUp::describe(now()->setTime(11, 0)));
        $this->assertSame('Tomorrow 11:00', FollowUp::describe(now()->addDay()->setTime(11, 0))['text']);
        $this->assertSame(['text' => '3d overdue', 'tone' => 'overdue'], FollowUp::describe(now()->subDays(3)));
        $this->assertSame('Yesterday', FollowUp::describe(now()->subDay())['text']);
    }

    public function test_avatars_use_initials_and_a_stable_colour(): void
    {
        $this->assertSame('PS', Avatar::initials('Dr. Priya Sharma'));
        $this->assertSame('K', Avatar::initials('Kiran'));
        $this->assertSame(Avatar::color('Kiran'), Avatar::color('Kiran'));
    }

    public function test_missing_pages_show_a_friendly_error(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound()->assertSee('Page not found')->assertSee('Go to home');
    }
}
