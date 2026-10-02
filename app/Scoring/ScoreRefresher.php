<?php

namespace App\Scoring;

use App\Models\Lead;
use App\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Keeps the stored score (used for sorting and badges) in step with the
 * lead. Won and lost leads have no score.
 */
final class ScoreRefresher
{
    /** Lead attributes that feed a signal; changing one rescores the lead. */
    public const INPUTS = ['status_id', 'source_id', 'value', 'priority', 'ai_insight', 'last_activity_at', 'last_inbound_at'];

    public function __construct(private readonly LeadScorer $scorer) {}

    /**
     * Rescore one lead and return the breakdown (null when it is closed).
     */
    public function refresh(Lead $lead): ?LeadScore
    {
        $lead->loadMissing(['organization', 'status', 'source']);
        $score = $lead->isOpen() ? $this->scorer->score($lead, ScoringContext::for($lead->organization)) : null;
        $this->store($lead, $score?->total);

        return $score;
    }

    /**
     * Rescore every lead of a workspace (hourly, so older activity counts
     * for less as time passes).
     */
    public function refreshWorkspace(Organization $organization): int
    {
        $context = ScoringContext::for($organization);
        $count = 0;

        $organization->leads()->with(['status', 'source'])->chunkById(200, function (Collection $leads) use ($context, &$count) {
            foreach ($leads as $lead) {
                $this->store($lead, $lead->isOpen() ? $this->scorer->score($lead, $context)->total : null);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Written straight to the table: a score is derived data, so it must
     * not count as an edit (no events, audit line or updated_at change).
     */
    private function store(Lead $lead, ?int $score): void
    {
        if ($lead->score === $score) {
            return;
        }

        Lead::query()->whereKey($lead->id)->toBase()->update(['score' => $score]);
        $lead->setAttribute('score', $score)->syncOriginalAttribute('score');
    }
}
