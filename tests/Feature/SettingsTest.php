<?php

namespace Tests\Feature;

use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_pipeline_statuses(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin);

        $this->post('/settings/statuses', ['name' => 'Proposal Sent', 'type' => 'open', 'color' => '#123456', 'sort_order' => 4])
            ->assertSessionHasNoErrors();
        $status = LeadStatus::where('name', 'Proposal Sent')->sole();

        $this->put("/settings/statuses/{$status->id}", ['name' => 'Quote Sent', 'type' => 'open', 'color' => '#654321', 'sort_order' => 5])
            ->assertSessionHasNoErrors();
        $this->assertSame('Quote Sent', $status->fresh()->name);

        $this->delete("/settings/statuses/{$status->id}")->assertSessionHasNoErrors();
        $this->assertNull($status->fresh());
    }

    public function test_a_status_in_use_or_the_last_open_status_cannot_be_removed(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin);
        $inUse = LeadStatus::where('name', 'New')->sole();
        Lead::factory()->withStatus($inUse)->create();

        $this->delete("/settings/statuses/{$inUse->id}")->assertSessionHasErrors('status');
        $this->assertNotNull($inUse->fresh());

        LeadStatus::where('type', StatusType::Open)->whereKeyNot($inUse->id)->delete();
        $this->put("/settings/statuses/{$inUse->id}", ['name' => 'New', 'type' => 'won', 'color' => '#000000', 'sort_order' => 1])
            ->assertSessionHasErrors('type');
        $this->assertSame(StatusType::Open, $inUse->fresh()->type);
    }

    public function test_status_and_source_names_are_unique_per_organization(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin);

        $this->post('/settings/statuses', ['name' => 'New', 'type' => 'open', 'color' => '#000000', 'sort_order' => 1])
            ->assertSessionHasErrors('name');
        $this->post('/settings/sources', ['name' => 'Website'])->assertSessionHasErrors('name');
    }

    public function test_deleting_a_source_keeps_its_leads(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin);
        $source = Source::where('name', 'Website')->sole();
        $lead = Lead::factory()->for($admin->organization)->create(['source_id' => $source->id]);

        $this->delete("/settings/sources/{$source->id}")->assertSessionHasNoErrors();

        $this->assertNull($lead->fresh()->source_id);
    }

    public function test_api_key_is_shown_once_and_stored_hashed(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)->post('/settings/workspace/api-key')->assertSessionHas('api_key');

        $key = session('api_key');
        $organization = Organization::find($admin->organization_id);
        $this->assertStringStartsWith('crm_', $key);
        $this->assertSame(hash('sha256', $key), $organization->api_key_hash);
        $this->assertArrayNotHasKey('api_key_hash', $organization->toArray());
    }
}
