<?php

namespace App\Scoring;

/**
 * A lead's score from 0 to 100 and the reasons that add up to it.
 */
final readonly class LeadScore
{
    public const HOT = 70;

    public const WARM = 40;

    /**
     * @param  list<ScoreFactor>  $factors  biggest influence first
     */
    public function __construct(public int $total, public array $factors) {}

    public function band(): string
    {
        return self::bandOf($this->total);
    }

    /** "hot", "warm" or "cold", so a stored score can be shown without rescoring. */
    public static function bandOf(int $score): string
    {
        return match (true) {
            $score >= self::HOT => 'hot',
            $score >= self::WARM => 'warm',
            default => 'cold',
        };
    }
}
