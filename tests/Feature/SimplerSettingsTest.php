<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimplerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_shows_six_everyday_pages_and_keeps_the_rest_under_advanced(): void
    {
        $html = $this->actingAs($this->registerOrganization())->get('/settings/workspace')->assertOk()->getContent();
        $nav = substr($html, strpos($html, 'class="settings-nav"'), strpos($html, 'class="settings-body"') - strpos($html, 'class="settings-nav"'));
        $everyday = substr($nav, 0, strpos($nav, 'settings-advanced'));

        preg_match_all('/<\/svg>([^<]+)<\/a>/', $everyday, $links);
        $this->assertSame(['General', 'Team', 'Integrations', 'Automation', 'Import &amp; export', 'Billing'], array_map('trim', $links[1]));
        $this->assertStringContainsString('<details class="settings-advanced" >', $nav, 'Advanced is closed on an everyday page');
        foreach (['Pipeline stages', 'Lead sources', 'Lost reasons', 'Custom fields', 'Webhooks', 'Privacy &amp; consent', 'Activity log'] as $advanced) {
            $this->assertStringContainsString($advanced, substr($nav, strpos($nav, 'settings-advanced')));
        }

        $this->get('/settings/webhooks')->assertSee('<details class="settings-advanced"  open >', false);
    }

    public function test_autopilot_routing_automations_and_sequences_are_one_automation_page(): void
    {
        $this->actingAs($this->registerOrganization());

        foreach (['/settings/autopilot' => 'Autopilot', '/settings/routing' => 'Lead routing', '/settings/automations' => 'Automations', '/settings/sequences' => 'Sequences', '/settings/sequences/create' => 'Sequences'] as $page => $tab) {
            $html = $this->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('aria-label="Automation"', $html, $page);
            $this->assertMatchesRegularExpression('#class="active"><svg[^>]*>((?!</a>).)*</svg>\s*'.$tab.'</a>#s', $html, "$page shows the $tab tab");
            $this->assertMatchesRegularExpression('#class="active"><svg[^>]*>((?!</a>).)*</svg>\s*Automation</a>#s', $html, "$page keeps Automation selected in the menu");
        }
    }

    public function test_autopilot_shows_five_essentials_and_folds_the_rest(): void
    {
        $html = $this->actingAs($this->registerOrganization())->get('/settings/autopilot')->getContent();
        [$essentials, $more] = explode('class="card auto-more"', $html, 2);
        $essentials = substr($essentials, strpos($essentials, 'The essentials'));

        preg_match_all('/name="([a-z_]+)" value="1"/', $essentials, $top);
        $this->assertSame(['first_follow_up', 'speed_to_lead', 'next_follow_up', 'away_message', 'daily_digest'], $top[1]);
        $this->assertStringStartsWith(' >', $more, 'More options starts closed');
        $this->assertSame(10, substr_count($more, 'class="auto-row'));

        // A mistake in a folded field opens the panel so it can be seen.
        $this->from('/settings/autopilot')->put('/settings/autopilot', ['work_start' => 9, 'work_end' => 18, 'reengage_days' => 500]);
        $this->assertStringContainsString('class="card auto-more"  open >', $this->get('/settings/autopilot')->getContent());
    }
}
