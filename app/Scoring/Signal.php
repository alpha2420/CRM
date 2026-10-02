<?php

namespace App\Scoring;

use App\Models\Lead;

/**
 * One thing that makes a lead more or less likely to buy. Each signal is
 * a small class, so adding or tuning one never touches the others.
 */
interface Signal
{
    /**
     * Points for this lead, with the reason, or null when it doesn't apply.
     */
    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor;
}
