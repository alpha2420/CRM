<?php

namespace App\Scoring\Signals;

use App\Models\Lead;
use App\Scoring\ScoreFactor;
use App\Scoring\ScoringContext;
use App\Scoring\Signal;

/** The AI assistant's read of the conversation, while it is still recent. */
final class AiTemperature implements Signal
{
    private const FRESH_FOR_DAYS = 14;

    public function evaluate(Lead $lead, ScoringContext $context): ?ScoreFactor
    {
        $at = $lead->ai_insight_at;

        if ($at === null || $at->diffInDays($context->now, true) > self::FRESH_FOR_DAYS) {
            return null;
        }

        return match ($lead->ai_insight['temperature'] ?? null) {
            'hot' => new ScoreFactor('AI rated this lead hot', 15),
            'warm' => new ScoreFactor('AI rated this lead warm', 5),
            'cold' => new ScoreFactor('AI rated this lead cold', -10),
            default => null,
        };
    }
}
