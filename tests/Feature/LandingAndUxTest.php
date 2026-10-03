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
            ->assertSee('Never lose a <em>lead</em> again.', false)
            ->assertSee('₹2,799')
            ->assertSee('Start free trial');
    }

    public function test_the_plan_question_matches_how_plans_are_changed_today(): void
    {
        config(['services.razorpay.key_id' => null, 'crm.support_email' => 'support@convera.test']);
        $this->get('/')
            ->assertSee("email support@convera.test and we'll switch it for you", false)
            ->assertDontSee('from Settings → Billing');

        config(['services.razorpay.key_id' => 'rzp_test_key', 'services.razorpay.key_secret' => 'secret']);
        $this->get('/')
            ->assertSee('Yes, any time, from Settings → Billing.')
            ->assertDontSee('switch it for you');
    }

    public function test_every_picture_on_the_website_exists(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match_all('#src="'.preg_quote(url('/'), '#').'/(images/[^"]+)"#', $html, $images);
        preg_match_all('#url\(\.\./(images/[^)]+)\)#', file_get_contents(public_path('css/landing.css')), $backgrounds);

        $files = array_unique([...$images[1], ...$backgrounds[1]]);
        $this->assertGreaterThanOrEqual(10, count($files), 'screenshots and textures');
        foreach ($files as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    public function test_the_website_always_uses_the_light_theme(): void
    {
        $this->get('/')->assertSee('<html lang="en" data-theme-locked>', false);
        $this->get('/privacy')->assertSee('<html lang="en" data-theme-locked>', false);
        $this->assertStringContainsString("hasAttribute('data-theme-locked')", file_get_contents(public_path('js/app.js')));
    }

    public function test_every_tab_on_the_landing_page_has_its_panel_and_style(): void
    {
        // The source demo and the product tour switch with radio buttons and CSS only.
        $html = $this->get('/')->getContent();
        $css = file_get_contents(public_path('css/landing.css'));

        foreach (['l-src' => ['data-src="%s"', '#%s:checked ~ .l-demo-panels'], 'l-tour' => ['data-tour="%s"', '#%s:checked ~ .l-tour-stage']] as $group => [$panel, $rule]) {
            preg_match_all('/name="'.$group.'" id="([a-z-]+)"/', $html, $tabs);
            $this->assertCount(5, $tabs[1], $group);

            foreach ($tabs[1] as $id) {
                $key = $group === 'l-src' ? $id : substr($id, strlen('tour-'));
                $this->assertStringContainsString('for="'.$id.'"', $html);
                $this->assertStringContainsString(sprintf($panel, $key), $html);
                $this->assertStringContainsString(sprintf($rule, $id), $css);
            }
        }
    }

    public function test_signed_in_users_skip_the_landing_page(): void
    {
        $this->actingAs($this->registerOrganization())->get('/')->assertRedirect('/dashboard');
    }

    public function test_new_workspaces_get_a_getting_started_checklist_they_can_dismiss(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)->get('/dashboard')->assertSee('Get your workspace ready')->assertSee('0 of 6 done')->assertSee('Set your working hours');

        Lead::factory()->for($admin->organization)->create();
        $this->get('/dashboard')->assertSee('1 of 6 done');

        $this->put('/settings/autopilot', $admin->organization->autopilot()->toArray());
        $this->get('/dashboard')->assertSee('2 of 6 done');

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
        // The workspace clock defaults to India; these times are local.
        $local = fn () => now('Asia/Kolkata');
        $this->travelTo($local()->setTime(10, 0));

        $this->assertSame(['text' => 'Today 11:00', 'tone' => 'today'], FollowUp::describe($local()->setTime(11, 0)));
        $this->assertSame('Tomorrow 11:00', FollowUp::describe($local()->addDay()->setTime(11, 0))['text']);
        $this->assertSame(['text' => '3d overdue', 'tone' => 'overdue'], FollowUp::describe($local()->subDays(3)));
        $this->assertSame('Yesterday', FollowUp::describe($local()->subDay())['text']);
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

    public function test_the_help_page_is_available_to_everyone_signed_in(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($agent)->get('/help')->assertOk()->assertSee('Why can&#039;t I type a free message?', false);
    }
}
