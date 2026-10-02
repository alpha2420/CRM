<?php

namespace App\Scoring\Signals;

use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/** A new enquiry is at its warmest. */
final class Freshness implements Signal
{
    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        $hours = $lead->created_at->diffInHours($context->now, true);

        return match (true) {
            $hours <= 24 => new ScoreFactor('New enquiry in the last 24 hours', 15),
            $hours <= 72 => new ScoreFactor('Enquired in the last 3 days', 5),
            default => null,
        };
    }
}
