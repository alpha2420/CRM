<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadCaptureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_a_valid_key_are_refused(): void
    {
        $this->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '9876543210'])->assertUnauthorized();
        $this->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '9876543210'], ['X-Api-Key' => 'crm_wrong'])->assertUnauthorized();
    }

    public function test_a_website_lead_is_created_and_assigned(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $key = app(ApiKeyManager::class)->regenerate($admin->organization);

        $this->postJson('/api/v1/leads', [
            'name' => 'Web Visitor', 'phone' => '+91 98765 43210', 'email' => 'web@example.com', 'source' => 'referral',
        ], ['X-Api-Key' => $key])->assertCreated()->assertJsonStructure(['id']);

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('+919876543210', $lead->phone);
        $this->assertSame($agent->id, $lead->assigned_to);
        $this->assertSame('Referral', $lead->source()->withoutGlobalScopes()->first()->name);
        $this->assertNull($lead->created_by);
    }

    public function test_unknown_source_falls_back_to_website(): void
    {
        $admin = $this->registerOrganization();
        $key = app(ApiKeyManager::class)->regenerate($admin->organization);

        $this->postJson('/api/v1/leads', ['name' => 'A', 'phone' => '9876543210', 'source' => 'Billboard'], ['X-Api-Key' => $key])
            ->assertCreated();

        $this->assertSame('Website', Lead::withoutGlobalScopes()->sole()->source()->withoutGlobalScopes()->first()->name);
    }

    public function test_repeat_enquiries_are_merged_and_invalid_data_rejected(): void
    {
        $admin = $this->registerOrganization();
        $key = app(ApiKeyManager::class)->regenerate($admin->organization);
        Lead::factory()->for($admin->organization)->create(['phone' => '+919876543210']);

        $this->postJson('/api/v1/leads', ['name' => 'Again', 'phone' => '+919876543210', 'notes' => 'Wants a demo'], ['X-Api-Key' => $key])
            ->assertOk()
            ->assertJsonPath('message', 'Lead already exists; the enquiry was added to its history.');
        $this->assertSame(1, Lead::withoutGlobalScopes()->count());
        $this->assertStringContainsString('Wants a demo', LeadActivity::withoutGlobalScopes()->sole()->note);
        $this->postJson('/api/v1/leads', ['phone' => 'abc'], ['X-Api-Key' => $key])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone']);
    }

    public function test_a_regenerated_key_replaces_the_old_one(): void
    {
        $admin = $this->registerOrganization();
        $keys = app(ApiKeyManager::class);
        $old = $keys->regenerate($admin->organization);
        $new = $keys->regenerate($admin->organization);

        $this->postJson('/api/v1/leads', ['name' => 'A', 'phone' => '9876543210'], ['X-Api-Key' => $old])->assertUnauthorized();
        $this->postJson('/api/v1/leads', ['name' => 'A', 'phone' => '9876543210'], ['X-Api-Key' => $new])->assertCreated();
    }
}
