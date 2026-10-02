<?php

namespace App\Scoring\Signals;

use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/**
 * Sources that have converted well for this workspace before are worth
 * more. Only used once a source has enough closed leads to judge.
 */
final class SourceQuality implements Signal
{
    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        $rate = $context->sourceWinRates[$lead->source_id] ?? null;
        $overall = $context->overallWinRate;

        if ($rate === null || $overall <= 0 || $lead->source === null) {
            return null;
        }

        $name = $lead->source->name;

        return match (true) {
            $rate >= 1.5 * $overall => new ScoreFactor("{$name} leads often turn into sales", 10),
            $rate >= $overall => new ScoreFactor("{$name} leads convert better than average", 5),
            $rate <= 0.5 * $overall => new ScoreFactor("{$name} leads rarely turn into sales", -5),
            default => null,
        };
    }
}
