<?php

namespace App\Scoring\Signals;

use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/** Bigger deals deserve attention first, compared with this workspace's average. */
final class DealValue implements Signal
{
    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        if ($lead->value === null || (float) $lead->value <= 0) {
            return null;
        }

        $value = (float) $lead->value;
        $average = $context->averageValue;

        return match (true) {
            $average > 0 && $value >= 2 * $average => new ScoreFactor('Deal value well above your average', 15),
            $average > 0 && $value >= $average => new ScoreFactor('Above-average deal value', 10),
            default => new ScoreFactor('Deal value recorded', 5),
        };
    }
}
