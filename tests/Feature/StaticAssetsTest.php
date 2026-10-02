<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_link_our_css_and_js_with_their_version(): void
    {
        $versioned = fn (string $path) => $path.'?v='.filemtime(public_path($path));

        $this->get('/')->assertSee($versioned('css/landing.css'), false)->assertSee($versioned('js/app.js'), false);
        $this->get('/login')->assertSee($versioned('css/app.css'), false)->assertSee($versioned('js/app.js'), false);
        $this->actingAs($this->registerOrganization())->get('/dashboard')
            ->assertSee($versioned('css/app.css'), false)
            ->assertSee($versioned('js/app.js'), false);
    }

    public function test_closed_dialogs_stay_hidden(): void
    {
        // A display rule on a dialog's own class beats the browser's "closed
        // dialogs are hidden", and the dialog then shows on every page.
        $css = file_get_contents(public_path('css/app.css'));

        foreach (['palette', 'shortcuts', 'call-sheet'] as $class) {
            preg_match_all('/^\.'.$class.' \{([^}]*)\}/m', $css, $rules);
            $this->assertNotEmpty($rules[1], ".$class has no rule");

            foreach ($rules[1] as $rule) {
                $this->assertStringNotContainsString('display', $rule, ".$class may only be shown when [open]");
            }
        }
    }
}
