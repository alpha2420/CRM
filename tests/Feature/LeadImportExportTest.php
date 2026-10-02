<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeadImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_valid_rows_and_reports_the_rest(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        Lead::factory()->for($admin->organization)->create(['phone' => '+911111111111']);

        $csv = "\xEF\xBB\xBFName,Phone,Email,Source,Status,Priority\n"
            ."Good One,+91 98765 43210,good@example.com,referral,contacted,high\n"
            ."Existing,+911111111111,,,,\n"
            ."No Phone,,,,,\n"
            ."In File Twice,+91 98765 43210,,,,\n"
            ."\n"
            ."Unknown Labels,9000000000,,Billboard,Mystery,urgent\n";

        $this->actingAs($admin)
            ->post('/leads/import', ['file' => UploadedFile::fake()->createWithContent('leads.csv', $csv)])
            ->assertRedirect('/leads/import')
            ->assertSessionHas('status', 'Imported 2 leads, skipped 3.');

        $good = Lead::where('phone', '+919876543210')->sole();
        $this->assertSame('Referral', $good->source->name);
        $this->assertSame('Contacted', $good->status->name);
        $this->assertSame('high', $good->priority->value);
        $this->assertSame($agent->id, $good->assigned_to);

        $unknown = Lead::where('phone', '+919000000000')->sole();
        $this->assertNull($unknown->source_id);
        $this->assertSame('New', $unknown->status->name);
        $this->assertSame('medium', $unknown->priority->value);
    }

    public function test_import_requires_name_and_phone_columns(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)
            ->post('/leads/import', ['file' => UploadedFile::fake()->createWithContent('leads.csv', "email\nx@example.com\n")])
            ->assertSessionHasErrors('file');
    }

    public function test_export_neutralises_spreadsheet_formulas_but_keeps_phone_numbers(): void
    {
        $admin = $this->registerOrganization();
        Lead::factory()->for($admin->organization)->create(['name' => '=HYPERLINK("http://evil")', 'phone' => '+919876543210']);

        $csv = $this->actingAs($admin)->get('/leads/export')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString(',+919876543210,', $csv);
    }

    public function test_agents_cannot_import_or_export(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($agent)->get('/leads/import')->assertForbidden();
        $this->get('/leads/export')->assertForbidden();
    }
}
