<?php

namespace Tests\Feature;

use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_carry_the_convera_logo_favicons_and_link_preview(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<title>Convera: the WhatsApp-first CRM for Indian businesses</title>', false)
            ->assertSee('class="logo-mark"', false)
            ->assertSee('<span class="logo-word">Convera</span>', false)
            ->assertSee('href="/favicon.svg"', false)
            ->assertSee('property="og:image" content="'.asset('images/og-image.jpg').'"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false);

        $this->get('/login')->assertSee('<span class="logo-word">Convera</span>', false)->assertSee('· Convera</title>', false);
        $this->assertSame('Convera', json_decode(file_get_contents(public_path('manifest.webmanifest')), true)['name']);

        foreach (['favicon.svg', 'favicon.ico', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-512.png', 'icons/apple-touch-icon.png', 'images/og-image.jpg', 'images/brand/convera-logo.svg', 'images/brand/convera-logo-white.svg', 'images/brand/convera-logo-email.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    public function test_the_app_and_its_emails_are_branded(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)->get('/leads')->assertOk()
            ->assertSee('<span class="logo-word">Convera</span>', false)
            ->assertSee('Leads · Convera</title>', false);

        $mail = (new WelcomeNotification)->toMail($admin);
        $this->assertSame('Welcome to Convera — your workspace is ready', $mail->subject);
        $html = (string) $mail->render();
        $this->assertStringContainsString(asset('images/brand/convera-logo-email.png'), $html);
        $this->assertStringContainsString('alt="Convera"', $html);
    }

    public function test_search_engines_get_a_sitemap_and_one_canonical_address(): void
    {
        $this->get('/')->assertSee('<link rel="canonical" href="'.url('/').'">', false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/').'</loc>', false)
            ->assertSee('<loc>'.route('legal', 'privacy').'</loc>', false)
            ->assertDontSee('dashboard');

        $this->assertStringContainsString('Sitemap: https://useconvera.com/sitemap.xml', file_get_contents(public_path('robots.txt')));
    }
}
