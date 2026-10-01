<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationTestConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_connection_check_shows_the_connected_number(): void
    {
        Http::fake(['graph.facebook.com/v25.0/PHONE_ID*' => Http::response(['display_phone_number' => '+91 98765 43210', 'verified_name' => 'Sunrise Realty', 'quality_rating' => 'GREEN'])]);
        $admin = $this->registerOrganization();
        $integration = $this->integration($admin, IntegrationType::WhatsApp, ['phone_number_id' => 'PHONE_ID', 'access_token' => 'token']);

        $this->actingAs($admin)->post('/settings/integrations/whatsapp/test')
            ->assertSessionHas('status', 'Connection works: +91 98765 43210 · Sunrise Realty (quality: GREEN).');

        $this->assertSame('+91 98765 43210 · Sunrise Realty (quality: GREEN)', $integration->fresh()->setting('verified_label'));
        $this->get('/settings/integrations/whatsapp')->assertSee('Sunrise Realty');
    }

    public function test_a_bad_token_shows_metas_reason(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token: Session has expired.']], 400)]);
        $admin = $this->registerOrganization();
        $this->integration($admin, IntegrationType::Facebook, ['page_access_token' => 'old']);

        $this->actingAs($admin)->post('/settings/integrations/facebook/test')
            ->assertSessionHasErrors(['connection' => 'Meta rejected the connection: Error validating access token: Session has expired. Check the token and IDs.']);
    }

    private function integration($admin, IntegrationType $type, array $settings): Integration
    {
        $integration = new Integration(['type' => $type, 'settings' => $settings]);
        $integration->organization_id = $admin->organization_id;
        $integration->save();

        return $integration;
    }
}
