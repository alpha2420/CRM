<?php

namespace App\Scoring\Signals;

use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/** Leads the team is actively working stay warm; neglected ones cool off. */
final class RecentFollowUp implements Signal
{
    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        $last = $lead->last_activity_at ?? $lead->created_at;
        $days = $last->diffInDays($context->now, true);

        return match (true) {
            $lead->last_activity_at !== null && $days <= 3 => new ScoreFactor('Followed up in the last 3 days', 10),
            $lead->last_activity_at !== null && $days <= 14 => new ScoreFactor('Followed up in the last 2 weeks', 5),
            $days > 14 => new ScoreFactor('No follow-up for over 2 weeks', -10),
            default => null,
        };
    }
}
