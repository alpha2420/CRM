<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_team_settings_and_sign_in_activity_is_recorded(): void
    {
        $admin = $this->registerOrganization();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->post('/leads', ['name' => 'Kiran', 'phone' => '9000000001', 'priority' => 'medium']);
        $lead = Lead::sole();
        $won = LeadStatus::where('type', 'won')->first();
        $this->put("/leads/{$lead->id}", ['name' => 'Kiran Rao', 'phone' => '9000000001', 'priority' => 'high', 'status_id' => $won->id]);
        $this->post('/users', ['name' => 'Asha', 'email' => 'asha@example.com', 'role' => 'agent', 'is_active' => '1', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $this->post('/settings/sources', ['name' => 'Google Ads']);
        $this->delete("/leads/{$lead->id}");

        $log = AuditLog::orderBy('id')->pluck('description', 'action');
        $this->assertSame('Signed in', $log['auth.login']);
        $this->assertSame('Added lead Kiran', $log['lead.created']);
        $this->assertSame('Updated Kiran Rao: name, status, priority', $log['lead.updated']);
        $this->assertSame('Added team member “Asha”', $log['user.created']);
        $this->assertSame('Added lead source “Google Ads”', $log['source.created']);
        $this->assertStringStartsWith('Deleted lead Kiran Rao', $log['lead.deleted']);
        $this->assertSame($admin->id, AuditLog::where('action', 'lead.created')->value('user_id'));

        $this->get('/settings/activity?area=lead')->assertOk()->assertSee('Added lead Kiran')->assertDontSee('Signed in');
    }

    public function test_an_import_is_one_entry_not_one_per_row(): void
    {
        $admin = $this->registerOrganization();
        $csv = "name,phone\nA,9000000001\nB,9000000002\nC,9000000003\n";

        $this->actingAs($admin)->post('/leads/import', ['file' => UploadedFile::fake()->createWithContent('leads.csv', $csv)]);

        $this->assertSame(0, AuditLog::where('action', 'lead.created')->count());
        $this->assertSame('Imported 3 leads from leads.csv', AuditLog::where('action', 'lead.imported')->value('description'));
    }

    public function test_the_log_is_private_to_each_workspace_and_to_admins(): void
    {
        $adminA = $this->registerOrganization('Alpha');
        $adminB = $this->registerOrganization('Beta');
        $agent = $this->addAgent($adminA->organization);
        $this->actingAs($adminB)->post('/settings/sources', ['name' => 'Beta Secret Source']);

        $this->actingAs($adminA)->get('/settings/activity')->assertOk()->assertDontSee('Beta Secret Source');
        $this->actingAs($agent)->get('/settings/activity')->assertForbidden();
    }

    public function test_old_entries_are_pruned_after_a_year(): void
    {
        $admin = $this->registerOrganization();
        $this->actingAs($admin)->post('/settings/sources', ['name' => 'Fresh']);
        AuditLog::query()->update(['created_at' => now()->subMonths(13)]);
        $this->post('/settings/sources', ['name' => 'Recent']);

        $this->artisan('model:prune', ['--model' => [AuditLog::class]]);

        $this->assertSame(['Added lead source “Recent”'], AuditLog::pluck('description')->all());
    }
}
