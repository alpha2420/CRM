<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\Source;
use App\Scoring\LeadScore;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoreRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadScoringTest extends TestCase
{
    use RefreshDatabase;

    private function stage(Organization $organization, string $name): LeadStatus
    {
        return $organization->leadStatuses()->where('name', $name)->sole();
    }

    /**
     * @return list<string>
     */
    private function reasons(?LeadScore $score): array
    {
        return array_map(fn (ScoreFactor $factor) => $factor->reason, $score->factors ?? []);
    }

    public function test_a_fresh_engaged_valuable_lead_scores_hot_and_says_why(): void
    {
        $organization = $this->registerOrganization()->organization;
        Lead::factory()->for($organization)->create(['value' => 100000, 'created_at' => now()->subMonth()]);
        $lead = Lead::factory()->for($organization)->create([
            'status_id' => $this->stage($organization, 'Interested')->id, // 4th of 5 open stages: +15
            'value' => 500000,                                            // above the 3L average: +10
            'priority' => Priority::High,                                  // +10
            'created_at' => now()->subHours(5),                            // +15
            'last_inbound_at' => now()->subDay(),                          // +25
            'last_activity_at' => now()->subDay(),                         // +10
        ]);

        $score = app(ScoreRefresher::class)->refresh($lead);

        $this->assertSame(100, $score->total, 'capped at 100');
        $this->assertSame('hot', $score->band());
        $this->assertSame('Replied on WhatsApp in the last 2 days', $score->factors[0]->reason, 'biggest reason first');
        $this->assertContains('At the Interested stage (4 of 5)', $this->reasons($score));
        $this->assertSame(100, $lead->fresh()->score);
    }

    public function test_neglected_leads_cool_off_and_closed_leads_have_no_score(): void
    {
        $organization = $this->registerOrganization()->organization;
        $quiet = Lead::factory()->for($organization)->create(['priority' => Priority::Low, 'created_at' => now()->subDays(20)]);
        $lost = Lead::factory()->for($organization)->create(['status_id' => $this->stage($organization, 'Lost')->id]);

        $score = app(ScoreRefresher::class)->refresh($quiet);

        $this->assertSame(15, $score->total); // 30 - 10 (no follow-up for 2 weeks) - 5 (low priority)
        $this->assertSame('cold', $score->band());
        $this->assertNull(app(ScoreRefresher::class)->refresh($lost));
        $this->assertNull($lost->fresh()->score);
    }

    public function test_sources_that_convert_well_score_higher_once_there_is_enough_history(): void
    {
        $organization = $this->registerOrganization()->organization;
        [$won, $lost] = [$this->stage($organization, 'Won')->id, $this->stage($organization, 'Lost')->id];
        $referral = Source::query()->where('name', 'Referral')->sole();
        $website = Source::query()->where('name', 'Website')->sole();
        $history = fn (Source $source, int $wins, int $losses) => [
            Lead::factory()->count($wins)->for($organization)->create(['source_id' => $source->id, 'status_id' => $won]),
            Lead::factory()->count($losses)->for($organization)->create(['source_id' => $source->id, 'status_id' => $lost]),
        ];

        $newFromReferral = Lead::factory()->for($organization)->create(['source_id' => $referral->id]);
        $this->assertNotContains('Referral leads often turn into sales', $this->reasons(app(ScoreRefresher::class)->refresh($newFromReferral)), 'no verdict without history');

        $history($referral, 8, 2);
        $history($website, 1, 9);
        $newFromWebsite = Lead::factory()->for($organization)->create(['source_id' => $website->id]);

        $this->assertContains('Referral leads often turn into sales', $this->reasons(app(ScoreRefresher::class)->refresh($newFromReferral)));
        $this->assertContains('Website leads rarely turn into sales', $this->reasons(app(ScoreRefresher::class)->refresh($newFromWebsite)));
    }

    public function test_scores_update_as_things_happen_and_hourly(): void
    {
        $admin = $this->registerOrganization();
        $lead = Lead::factory()->for($admin->organization)->create();
        $this->assertSame(45, $lead->fresh()->score, 'scored when created: 30 + 15 for a new enquiry');

        $lead->update(['priority' => Priority::High]);
        $this->assertSame(55, $lead->fresh()->score, 'rescored when the priority changes');

        $this->actingAs($admin)->post("/leads/{$lead->id}/activities", ['status_id' => $this->stage($admin->organization, 'Interested')->id, 'note' => 'Called']);
        $this->assertSame(80, $lead->fresh()->score, 'rescored after a follow-up: +10 recent follow-up, +15 stage');

        $this->travel(20)->days();
        $this->artisan('crm:score-leads')->assertSuccessful();
        $this->assertSame(45, $lead->fresh()->score, 'the hourly run lets old activity fade');
    }

    public function test_the_list_sorts_by_score_and_the_lead_page_explains_it(): void
    {
        $admin = $this->registerOrganization();
        $cold = Lead::factory()->for($admin->organization)->create(['name' => 'Cold Prospect', 'created_at' => now()->subDays(20), 'priority' => Priority::Low]);
        $hot = Lead::factory()->for($admin->organization)->create(['name' => 'Hot Prospect', 'created_at' => now()->subDays(20), 'last_inbound_at' => now()]);
        app(ScoreRefresher::class)->refresh($cold);
        app(ScoreRefresher::class)->refresh($hot);

        $this->actingAs($admin)->get('/leads')->assertSeeInOrder(['Hot Prospect', 'Cold Prospect']);
        $this->get('/leads?sort=score')->assertOk()->assertSeeInOrder(['Hot Prospect', 'Cold Prospect']);
        $hot->forceFill(['created_at' => now()->addMinute()])->saveQuietly();
        $cold->forceFill(['created_at' => now()->addMinutes(2)])->saveQuietly();
        $this->get('/leads')->assertSeeInOrder(['Cold Prospect', 'Hot Prospect'], 'newest first by default');
        $this->get('/leads?sort=score')->assertSeeInOrder(['Hot Prospect', 'Cold Prospect']);

        $this->get("/leads/{$hot->id}")->assertSee('Lead score')->assertSee('Replied on WhatsApp in the last 2 days');
    }
}
