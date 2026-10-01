<?php

namespace App\Ai;

/**
 * Turns a prompt about a lead into a LeadInsight. Implemented by the
 * Claude adapter in production and by a fake in tests.
 */
interface InsightGenerator
{
    /**
     * @throws AssistantException when no usable answer comes back
     */
    public function generate(string $system, string $prompt): LeadInsight;
}
