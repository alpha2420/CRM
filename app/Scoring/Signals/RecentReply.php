<?php

namespace App\Scoring\Signals;

use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/** A lead who writes back is engaged; the more recent, the better. */
final class RecentReply implements Signal
{
    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        if ($lead->last_inbound_at === null) {
            return null;
        }

        $days = $lead->last_inbound_at->diffInDays($context->now, true);

        return match (true) {
            $days <= 2 => new ScoreFactor('Replied on WhatsApp in the last 2 days', 25),
            $days <= 7 => new ScoreFactor('Replied on WhatsApp this week', 15),
            $days <= 30 => new ScoreFactor('Replied on WhatsApp this month', 5),
            default => null,
        };
    }
}
