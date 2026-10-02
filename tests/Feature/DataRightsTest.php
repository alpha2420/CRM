<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class DataRightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_download_everything_as_a_zip_of_their_own_data(): void
    {
        $admin = $this->registerOrganization('Alpha');
        $other = $this->registerOrganization('Beta');
        Lead::factory()->for($admin->organization)->create(['name' => 'Alpha Lead']);
        Lead::factory()->for($other->organization)->create(['name' => 'Beta Lead']);

        $this->actingAs($admin)->post('/settings/data/export', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $response = $this->post('/settings/data/export', ['password' => 'password'])->assertOk();

        // In production the file is deleted once sent; a test response is never sent.
        $archive = $response->baseResponse->getFile()->getPathname();
        $this->beforeApplicationDestroyed(fn () => @unlink($archive));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive));
        foreach (['README.txt', 'leads.csv', 'follow_ups.csv', 'whatsapp_messages.csv', 'team.csv', 'pipeline.csv', 'activity_log.csv'] as $file) {
            $this->assertNotFalse($zip->locateName($file), "{$file} is missing");
        }
        $leads = $zip->getFromName('leads.csv');
        $this->assertStringContainsString('Alpha Lead', $leads);
        $this->assertStringNotContainsString('Beta Lead', $leads);
        $this->assertStringNotContainsString($other->email, $zip->getFromName('team.csv'));
        $zip->close();
        $this->assertSame('Downloaded a full data export', AuditLog::where('action', 'data.exported')->value('description'));
    }

    public function test_deleting_a_workspace_erases_only_that_workspace(): void
    {
        $admin = $this->registerOrganization('Alpha');
        $agent = $this->addAgent($admin->organization);
        $other = $this->registerOrganization('Beta');
        $lead = Lead::factory()->for($admin->organization)->create();
        $this->actingAs($admin)->post("/leads/{$lead->id}/activities", ['status_id' => $lead->status_id, 'note' => 'Called']);
        Lead::factory()->for($other->organization)->create(['name' => 'Survivor']);
        $orgId = $admin->organization_id;

        $this->delete('/settings/workspace', ['password' => 'password', 'confirm_name' => 'alpha'])->assertSessionHasErrors('confirm_name');
        $this->delete('/settings/workspace', ['password' => 'nope', 'confirm_name' => 'Alpha'])->assertSessionHasErrors('password');
        $this->assertNotNull(Organization::find($orgId));

        $this->delete('/settings/workspace', ['password' => 'password', 'confirm_name' => 'Alpha'])->assertRedirect('/');
        $this->assertGuest();

        $this->assertNull(Organization::find($orgId));
        $this->assertSame(0, User::where('organization_id', $orgId)->count());
        $this->assertNull(User::find($agent->id));
        $this->assertSame(0, Lead::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->count());
        $this->assertSame(0, LeadActivity::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->count());
        $this->assertSame(0, LeadStatus::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->count());
        $this->assertSame(0, AuditLog::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->count());

        $this->assertSame('Survivor', Lead::withoutGlobalScope(OrganizationScope::class)->sole()->name);
        $this->post('/login', ['email' => $other->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_agents_cannot_export_or_delete_the_workspace(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($agent)->post('/settings/data/export', ['password' => 'password'])->assertForbidden();
        $this->delete('/settings/workspace', ['password' => 'password', 'confirm_name' => 'Acme'])->assertForbidden();
    }

    public function test_privacy_and_terms_pages_are_public(): void
    {
        config(['crm.support_email' => 'help@example.com']);

        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy')->assertSee('Grievance Officer')->assertSee('help@example.com');
        $this->get('/terms')->assertOk()->assertSee('Terms of Service');
        $this->get('/register')->assertSee('/terms', false)->assertSee('/privacy', false);
        $this->get('/')->assertSee('/privacy', false);
    }
}
