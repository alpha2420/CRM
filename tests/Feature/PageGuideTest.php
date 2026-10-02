<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PageGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_guide_a_page_asks_for_is_complete_and_links_to_a_real_help_topic(): void
    {
        $topics = collect(__('help.topics'))->pluck(0);
        $pages = collect(File::allFiles(resource_path('views')))
            ->map(fn ($file) => preg_match("/@section\\('guide', '([a-z_]+)'\\)/", $file->getContents(), $m) ? $m[1] : null)
            ->filter()
            ->values();

        $this->assertGreaterThanOrEqual(19, $pages->count());

        foreach ($pages as $page) {
            $guide = __("guide.{$page}");
            $this->assertIsArray($guide, "guide.{$page} is missing");
            $this->assertNotEmpty($guide['what'], $page);
            $this->assertNotEmpty($guide['why'], $page);
            $this->assertGreaterThanOrEqual(2, count($guide['steps']), $page);
            $this->assertContains($guide['help'], $topics, "guide.{$page} links to a Help topic that does not exist");
        }
    }

    public function test_pages_explain_themselves_to_admins_and_agents(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($admin)->get('/dashboard')->assertSee('How this page works')->assertSee(__('guide.dashboard.what'))
            ->assertSee('Start with <b>Today&#039;s follow-ups</b>', false);
        $this->get('/settings/autopilot')->assertSee(__('guide.autopilot.what'))->assertSee('data-guide="autopilot"', false);
        $this->get('/settings/integrations')->assertSee(__('guide.integrations.what'));
        $this->get('/reports')->assertSee(__('guide.reports.what'))->assertSee(route('help').'#reports', false);

        $this->actingAs($agent)->get('/leads')->assertSee(__('guide.leads.what'));
        $this->get('/today')->assertSee(__('guide.today.what'));
    }

    public function test_help_covers_the_new_features(): void
    {
        $this->actingAs($this->registerOrganization())->get('/help')->assertOk()
            ->assertSee('My day and to-dos')
            ->assertSee('What happens if a lead replies STOP?')
            ->assertSee('What does &quot;Answered in 5 min&quot; mean?', false)
            ->assertSee('Shortcuts and dark mode')
            ->assertSee('Dormant: open but no follow-up for '.config('crm.dormant_after_days').' days');
    }
}
