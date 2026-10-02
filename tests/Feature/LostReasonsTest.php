<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\LostReason;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LostReasonsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(Carbon::parse('2030-03-12 12:00', 'Asia/Kolkata')); // a Tuesday, inside working hours
        $this->admin = $this->registerOrganization();
        $this->actingAs($this->admin);
    }

    private function stage(string $name): LeadStatus
    {
        return $this->admin->organization->leadStatuses()->where('name', $name)->sole();
    }

    private function reason(string $name): LostReason
    {
        return LostReason::query()->where('name', $name)->sole();
    }

    public function test_workspaces_start_with_reasons_that_admins_can_change(): void
    {
        $this->assertSame(['Price too high', 'Chose a competitor', 'Not the right time', 'Stopped responding', 'Not interested', 'Other'],
            LostReason::query()->ordered()->pluck('name')->all());

        $this->get('/settings/lost-reasons')->assertOk()->assertSee('Price too high');
        $this->post('/settings/lost-reasons', ['name' => 'Budget not approved', 'win_back_after_days' => 90]);
        $this->put('/settings/lost-reasons/'.$this->reason('Other')->id, ['name' => 'Other', 'win_back_after_days' => 3])->assertSessionHasErrors('win_back_after_days');
        $this->assertSame(90, $this->reason('Budget not approved')->win_back_after_days);

        $this->actingAs($this->addAgent($this->admin->organization))->get('/settings/lost-reasons')->assertForbidden();
    }

    public function test_the_reason_is_recorded_when_a_lead_is_lost_and_cleared_when_it_reopens(): void
    {
        $byFollowUp = Lead::factory()->for($this->admin->organization)->create();
        $byMenu = Lead::factory()->for($this->admin->organization)->create();
        $fromBoard = Lead::factory()->for($this->admin->organization)->create();

        $this->post("/leads/{$byFollowUp->id}/activities", ['status_id' => $this->stage('Lost')->id, 'note' => 'Went elsewhere', 'lost_reason_id' => $this->reason('Chose a competitor')->id]);
        $this->patch("/leads/{$byMenu->id}/status", ['status_id' => $this->stage('Lost')->id, 'lost_reason_id' => $this->reason('Price too high')->id]);
        $this->patchJson("/leads/{$fromBoard->id}/status", ['status_id' => $this->stage('Lost')->id]);

        $this->assertSame('Chose a competitor', $byFollowUp->fresh()->lostReason->name);
        $this->assertSame('Price too high', $byMenu->fresh()->lostReason->name);
        $this->get("/leads/{$fromBoard->id}")->assertSee('id="why-lost"', false);
        $this->patch("/leads/{$fromBoard->id}/lost-reason", ['lost_reason_id' => $this->reason('Not the right time')->id])->assertSessionHas('status', 'Lost reason saved.');
        $this->get("/leads/{$fromBoard->id}")->assertSee('<strong>Not the right time</strong>', false)->assertDontSee('id="why-lost"', false);

        $this->patch("/leads/{$byMenu->id}/status", ['status_id' => $this->stage('Contacted')->id]);
        $this->assertNull($byMenu->fresh()->lost_reason_id, 'a reopened lead is no longer lost');
        $this->patch("/leads/{$byMenu->id}/lost-reason", ['lost_reason_id' => $this->reason('Other')->id])->assertStatus(422);
    }

    public function test_win_back_reopens_lost_leads_after_their_reasons_delay_once(): void
    {
        $whatsapp = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => ['phone_number_id' => 'P', 'waba_id' => 'W', 'access_token' => 't', 'app_secret' => 's', 'default_country_code' => '91']]);
        $whatsapp->organization_id = $this->admin->organization_id;
        $whatsapp->save();
        $template = WhatsAppTemplate::query()->make(['name' => 'come_back', 'language' => 'en', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, we have a new offer!', 'variables' => 1]);
        $template->organization_id = $this->admin->organization_id;
        $template->save();
        $lost = fn (string $reason, int $daysAgo) => Lead::factory()->for($this->admin->organization)->create([
            'name' => 'Kiran Rao', 'status_id' => $this->stage('Lost')->id,
            'lost_reason_id' => $this->reason($reason)->id, 'closed_at' => now()->subDays($daysAgo),
        ]);
        $priceLongAgo = $lost('Price too high', 31);  // 30-day delay: due
        $priceRecent = $lost('Price too high', 10);   // not yet
        $competitor = $lost('Chose a competitor', 200); // never

        $this->artisan('crm:win-back')->expectsOutput('Won back 0 leads.'); // off by default

        $this->admin->organization->forceFill(['autopilot' => ['win_back' => true, 'win_back_template_id' => $template->id]])->save();
        $this->artisan('crm:win-back')->expectsOutput('Won back 1 leads.');
        $this->artisan('crm:win-back')->expectsOutput('Won back 0 leads.');

        $this->assertSame('New', $priceLongAgo->fresh()->status->name);
        $this->assertNotNull($priceLongAgo->fresh()->win_back_at);
        $this->assertSame('Hi Kiran, we have a new offer!', WhatsAppMessage::sole()->body);
        $this->assertSame('Lost', $priceRecent->fresh()->status->name);
        $this->assertSame('Lost', $competitor->fresh()->status->name);
        $this->assertStringContainsString('win-back, 30 days after it was lost (Price too high)', $priceLongAgo->activities()->latest('id')->first()->note);
    }

    public function test_reports_show_why_leads_were_lost(): void
    {
        $lost = $this->stage('Lost')->id;
        Lead::factory()->count(2)->for($this->admin->organization)->create(['status_id' => $lost, 'lost_reason_id' => $this->reason('Price too high')->id, 'closed_at' => now()->subDay()]);
        Lead::factory()->for($this->admin->organization)->create(['status_id' => $lost, 'closed_at' => now()->subDay()]);

        $this->get('/reports')->assertOk()->assertSee('Why leads were lost')->assertSeeInOrder(['Price too high', '2', 'No reason given', '1']);
    }
}
