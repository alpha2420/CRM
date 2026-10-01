<?php

namespace Tests\Feature;

use App\Enums\StatusType;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomFieldsAndBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_fields_are_defined_filled_validated_and_exported(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin);

        $this->post('/settings/custom-fields', ['label' => 'Property type', 'type' => 'select', 'options' => '1BHK, 2BHK, 3BHK', 'is_required' => '1', 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->post('/settings/custom-fields', ['label' => 'Budget', 'type' => 'number', 'options' => '', 'sort_order' => 2])->assertSessionHasNoErrors();
        $this->post('/settings/custom-fields', ['label' => 'Phone', 'type' => 'text', 'sort_order' => 3])->assertSessionHasErrors('key');

        $this->post('/leads', ['name' => 'Asha', 'phone' => '9000000001', 'priority' => 'medium', 'custom' => ['property_type' => '4BHK']])
            ->assertSessionHasErrors('custom.property_type');
        $this->post('/leads', ['name' => 'Asha', 'phone' => '9000000001', 'priority' => 'medium', 'custom' => ['property_type' => '2BHK', 'budget' => '4500000']])
            ->assertSessionHasNoErrors();

        $lead = Lead::sole();
        $this->assertEqualsCanonicalizing(['property_type' => '2BHK', 'budget' => '4500000'], $lead->custom_values);
        $this->get("/leads/{$lead->id}")->assertSee('Property type')->assertSee('2BHK');

        $csv = $this->get('/leads/export')->streamedContent();
        $this->assertStringContainsString('"Property type",Budget', $csv);
        $this->assertStringContainsString('2BHK,4500000', $csv);
    }

    public function test_renaming_a_field_keeps_its_data_and_import_reads_custom_columns(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin)->post('/settings/custom-fields', ['label' => 'Course', 'type' => 'text', 'sort_order' => 1]);
        $field = CustomField::sole();

        $this->put("/settings/custom-fields/{$field->id}", ['label' => 'Course interested in', 'type' => 'text', 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->assertSame('course', $field->fresh()->key);

        $csv = "name,phone,Course interested in\nRavi,9000000002,MBA\n";
        $this->post('/leads/import', ['file' => UploadedFile::fake()->createWithContent('leads.csv', $csv)])->assertSessionHas('status', 'Imported 1 leads, skipped 0.');
        $this->assertSame(['course' => 'MBA'], Lead::sole()->custom_values);
    }

    public function test_admins_bulk_update_assign_and_delete_selected_leads(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $leads = Lead::factory()->for($admin->organization)->count(3)->create();
        $won = LeadStatus::where('type', StatusType::Won)->first();
        $ids = $leads->pluck('id')->all();

        $this->actingAs($admin)->post('/leads/bulk', ['ids' => $ids, 'operation' => "status:{$won->id}"])->assertSessionHas('status', '3 leads updated.');
        $this->assertSame(3, Lead::where('status_id', $won->id)->whereNotNull('closed_at')->count());

        $this->post('/leads/bulk', ['ids' => $ids, 'operation' => "assign:{$agent->id}"]);
        $this->assertSame(3, Lead::where('assigned_to', $agent->id)->count());

        $this->post('/leads/bulk', ['ids' => [$ids[0]], 'operation' => 'delete']);
        $this->assertSame(2, Lead::count());
    }

    public function test_agents_can_only_bulk_change_status_on_their_own_leads(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $other = $this->addAgent($admin->organization);
        $mine = Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id]);
        $theirs = Lead::factory()->for($admin->organization)->create(['assigned_to' => $other->id]);
        $contacted = LeadStatus::where('name', 'Contacted')->first();

        $this->actingAs($agent)->post('/leads/bulk', ['ids' => [$mine->id, $theirs->id], 'operation' => "status:{$contacted->id}"]);
        $this->assertSame($contacted->id, $mine->fresh()->status_id);
        $this->assertNotSame($contacted->id, $theirs->fresh()->status_id);

        $this->post('/leads/bulk', ['ids' => [$mine->id], 'operation' => 'delete'])->assertSessionHasErrors('action');
        $this->assertNotNull($mine->fresh());
    }

    public function test_bulk_actions_cannot_touch_another_workspace(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $adminB = $this->registerOrganization('Beta');
        $foreign = Lead::factory()->for($adminB->organization)->create();

        $this->actingAs($adminA)->post('/leads/bulk', ['ids' => [$foreign->id], 'operation' => 'delete']);

        $this->assertNotNull(Lead::withoutGlobalScopes()->find($foreign->id));
    }
}
