<?php

namespace App\Scoring;

use App\Models\Lead;
use App\Scoring\Signals\AiTemperature;
use App\Scoring\Signals\DealValue;
use App\Scoring\Signals\Freshness;
use App\Scoring\Signals\PriorityLevel;
use App\Scoring\Signals\RecentFollowUp;
use App\Scoring\Signals\RecentReply;
use App\Scoring\Signals\SourceQuality;
use App\Scoring\Signals\StageProgress;

/**
 * Adds up the signals into a score from 0 to 100. Every lead starts at
 * BASE; each signal moves it up or down and says why.
 */
final class LeadScorer
{
    public const BASE = 30;

    /** @var list<Signal> */
    private array $signals;

    /**
     * @param  list<Signal>|null  $signals  defaults to every signal in Signals/
     */
    public function __construct(?array $signals = null)
    {
        $this->signals = $signals ?? [
            new Freshness,
            new RecentReply,
            new RecentFollowUp,
            new StageProgress,
            new DealValue,
            new SourceQuality,
            new PriorityLevel,
            new AiTemperature,
        ];
    }

    public function score(Lead $lead, ScoringContext $context): LeadScore
    {
        $factors = [];
        foreach ($this->signals as $signal) {
            if ($factor = $signal->evaluate($lead, $context)) {
                $factors[] = $factor;
            }
        }

        usort($factors, fn (ScoreFactor $a, ScoreFactor $b) => abs($b->points) <=> abs($a->points));
        $total = self::BASE + array_sum(array_map(fn (ScoreFactor $f) => $f->points, $factors));

        return new LeadScore(max(0, min(100, $total)), $factors);
    }
}
