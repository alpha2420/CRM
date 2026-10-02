<?php

namespace Tests;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationRegistrar;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * A fully set-up workspace (trial, default statuses and sources) and
     * its admin, whose email is already verified.
     */
    protected function registerOrganization(string $name = 'Acme'): User
    {
        $admin = app(OrganizationRegistrar::class)
            ->register($name, "{$name} Admin", Str::lower(Str::random(10)).'@example.com', 'password');
        $admin->markEmailAsVerified();

        return $admin;
    }

    protected function addAgent(Organization $organization, array $attributes = []): User
    {
        return User::factory()->for($organization)->create($attributes);
    }

    /** WhatsApp connected with test credentials (app secret "app-secret"). */
    protected function connectWhatsApp(Organization $organization): Integration
    {
        $integration = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => [
            'phone_number_id' => 'PHONE_ID', 'waba_id' => 'WABA_ID', 'access_token' => 'token-123',
            'app_secret' => 'app-secret', 'verify_token' => 'verify-me', 'default_country_code' => '91',
        ]]);
        $integration->organization_id = $organization->id;
        $integration->save();

        return $integration;
    }

    /**
     * Deliver a signed WhatsApp webhook with these incoming messages.
     *
     * @param  list<array<string, mixed>>  $messages
     */
    protected function whatsAppWebhook(Integration $whatsapp, array $messages, ?string $name = null): TestResponse
    {
        $json = (string) json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
            'contacts' => $name ? [['wa_id' => $messages[0]['from'], 'profile' => ['name' => $name]]] : [],
            'messages' => $messages,
        ]]]]]]);

        $response = $this->call('POST', "/api/webhooks/meta/{$whatsapp->webhook_key}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, 'app-secret'),
        ], $json);

        // A real request ends here: forget the workspace the webhook acted for.
        $this->app->forgetScopedInstances();

        return $response;
    }
}
