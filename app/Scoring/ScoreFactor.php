<?php

namespace App\Scoring;

/**
 * One reason behind a lead's score, e.g. "Replied on WhatsApp today, +25".
 */
final readonly class ScoreFactor
{
    public function __construct(public string $reason, public int $points) {}
}
