<?php

namespace App\Scoring\Signals;

use App\Enums\Priority;
use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/** The team's own judgement, through the priority they set. */
final class PriorityLevel implements Signal
{
    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        return match ($lead->priority) {
            Priority::High => new ScoreFactor('Marked high priority', 10),
            Priority::Low => new ScoreFactor('Marked low priority', -5),
            default => null,
        };
    }
}
