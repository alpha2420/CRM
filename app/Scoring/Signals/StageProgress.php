<?php

namespace App\Scoring\Signals;

use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/** The further along the pipeline, the closer to a sale (up to +20). */
final class StageProgress implements Signal
{
    private const MAX = 20;

    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        $position = array_search($lead->status_id, $context->openStages, true);
        $stages = count($context->openStages);

        if ($position === false || $position === 0 || $stages < 2) {
            return null;
        }

        $points = (int) round($position / ($stages - 1) * self::MAX);

        return new ScoreFactor("At the {$lead->status->name} stage (".($position + 1)." of {$stages})", $points);
    }
}
