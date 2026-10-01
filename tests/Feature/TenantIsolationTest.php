<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The most important guarantee of a SaaS: one customer can never read or
 * change another customer's data.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leads_of_another_organization_are_invisible(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $adminB = $this->registerOrganization('Beta');
        $ownLead = Lead::factory()->for($adminA->organization)->create(['name' => 'Alpha Lead']);
        $foreignLead = Lead::factory()->for($adminB->organization)->create(['name' => 'Beta Lead']);

        $this->actingAs($adminA)
            ->get('/leads')
            ->assertOk()
            ->assertSee('Alpha Lead')
            ->assertDontSee('Beta Lead');

        $this->get("/leads/{$foreignLead->id}")->assertNotFound();
        $this->get("/leads/{$foreignLead->id}/edit")->assertNotFound();
        $this->put("/leads/{$foreignLead->id}", ['name' => 'Hacked', 'phone' => '12345', 'priority' => 'low', 'status_id' => $ownLead->status_id])->assertNotFound();
        $this->delete("/leads/{$foreignLead->id}")->assertNotFound();
        $this->post("/leads/{$foreignLead->id}/activities", ['status_id' => $ownLead->status_id])->assertNotFound();

        $this->assertSame('Beta Lead', $foreignLead->fresh()->name);
        $this->assertSame(0, $foreignLead->activities()->withoutGlobalScopes()->count());
    }

    public function test_foreign_ids_are_rejected_when_saving_a_lead(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $adminB = $this->registerOrganization('Beta');
        $foreignStatus = LeadStatus::withoutGlobalScopes()->where('organization_id', $adminB->organization_id)->first();
        $foreignSource = Source::withoutGlobalScopes()->where('organization_id', $adminB->organization_id)->first();

        $this->actingAs($adminA)
            ->post('/leads', [
                'name' => 'Sneaky',
                'phone' => '9876543210',
                'priority' => 'medium',
                'status_id' => $foreignStatus->id,
                'source_id' => $foreignSource->id,
                'assigned_to' => $adminB->id,
            ])
            ->assertSessionHasErrors(['status_id', 'source_id', 'assigned_to']);

        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
    }

    public function test_settings_and_users_of_another_organization_cannot_be_changed(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $adminB = $this->registerOrganization('Beta');
        $foreignStatus = LeadStatus::withoutGlobalScopes()->where('organization_id', $adminB->organization_id)->first();
        $foreignSource = Source::withoutGlobalScopes()->where('organization_id', $adminB->organization_id)->first();

        $this->actingAs($adminA);
        $this->put("/settings/statuses/{$foreignStatus->id}", ['name' => 'X', 'type' => 'open', 'color' => '#000000', 'sort_order' => 1])->assertNotFound();
        $this->delete("/settings/sources/{$foreignSource->id}")->assertNotFound();
        $this->get("/users/{$adminB->id}/edit")->assertNotFound();
        $this->put("/users/{$adminB->id}", ['name' => 'X', 'email' => 'x@example.com', 'role' => 'agent'])->assertNotFound();
        $this->delete("/users/{$adminB->id}")->assertNotFound();

        $this->assertNotSame('X', $foreignStatus->fresh()->name);
        $this->assertNotNull($foreignSource->fresh());
        $this->assertNotNull($adminB->fresh());
    }

    public function test_the_same_phone_number_can_exist_in_two_organizations(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $adminB = $this->registerOrganization('Beta');
        Lead::factory()->for($adminB->organization)->create(['phone' => '+919876543210']);

        $this->actingAs($adminA)
            ->post('/leads', ['name' => 'Same Phone', 'phone' => '+91 98765 43210', 'priority' => 'medium'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Lead::withoutGlobalScopes()->where('phone', '+919876543210')->count());
    }

    public function test_export_contains_only_own_leads(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $adminB = $this->registerOrganization('Beta');
        Lead::factory()->for($adminA->organization)->create(['name' => 'Alpha Lead']);
        Lead::factory()->for($adminB->organization)->create(['name' => 'Beta Lead']);

        $csv = $this->actingAs($adminA)->get('/leads/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('Alpha Lead', $csv);
        $this->assertStringNotContainsString('Beta Lead', $csv);
    }

    public function test_api_key_only_writes_into_its_own_organization(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $this->registerOrganization('Beta');
        $key = app(ApiKeyManager::class)->regenerate($adminA->organization);

        $this->postJson('/api/v1/leads', ['name' => 'Web Lead', 'phone' => '9876543210'], ['X-Api-Key' => $key])
            ->assertCreated();

        $this->assertSame($adminA->organization_id, Lead::withoutGlobalScopes()->sole()->organization_id);
    }
}
