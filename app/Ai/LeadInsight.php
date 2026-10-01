<?php

namespace App\Ai;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

/**
 * The shape Claude returns for a lead (enforced by structured outputs).
 */
class LeadInsight implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'Two or three sentences: who the lead is, what they want, and where things stand.')]
    public string $summary;

    #[Constrained(description: 'Exactly one of: hot, warm, cold.')]
    public string $temperature;

    #[Constrained(description: 'One short sentence explaining the temperature.')]
    public string $reason;

    #[Constrained(description: 'The single most useful next action for the sales agent, in one sentence.')]
    public string $next_step;

    #[Constrained(description: 'A WhatsApp message the agent can send now, in the language and tone the lead uses, under 60 words, with no placeholders.')]
    public string $suggested_message;

    /**
     * @return array{summary: string, temperature: string, reason: string, next_step: string, suggested_message: string}
     */
    public function toStoredArray(): array
    {
        $temperature = strtolower(trim($this->temperature));

        return [
            'summary' => trim($this->summary),
            'temperature' => in_array($temperature, ['hot', 'warm', 'cold'], true) ? $temperature : 'warm',
            'reason' => trim($this->reason),
            'next_step' => trim($this->next_step),
            'suggested_message' => trim($this->suggested_message),
        ];
    }
}
