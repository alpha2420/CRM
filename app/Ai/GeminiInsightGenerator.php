<?php

namespace App\Ai;

/**
 * Lead insights from Google Gemini, answered in JSON that matches
 * LeadInsight.
 */
final class GeminiInsightGenerator implements InsightGenerator
{
    private readonly Gemini $gemini;

    public function __construct(string $apiKey, string $model, ?string $fallbackModel = null)
    {
        $this->gemini = new Gemini($apiKey, $model, $fallbackModel);
    }

    public function generate(string $system, string $prompt): LeadInsight
    {
        $answer = $this->gemini->json($system, [['text' => $prompt]], LeadInsight::schema());

        if ($answer === null || ! LeadInsight::isComplete($answer)) {
            throw new AssistantException('The AI assistant could not analyse this lead.');
        }

        return LeadInsight::fromArray($answer);
    }
}
