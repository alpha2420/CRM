<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Claude via the official Anthropic PHP SDK, with structured outputs so the
 * reply always matches LeadInsight. Server-side fallback is enabled: if the
 * model declines for policy reasons, the API retries on its default
 * fallback model instead of failing.
 */
final class ClaudeInsightGenerator implements InsightGenerator
{
    public function __construct(private readonly Client $client, private readonly string $model) {}

    public function generate(string $system, string $prompt): LeadInsight
    {
        try {
            $message = $this->client->beta->messages->create(
                model: $this->model,
                maxTokens: 16000,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                // A short summary does not need deep reasoning; low effort keeps it fast and cheap.
                outputConfig: ['effort' => 'low', 'format' => LeadInsight::class],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (RateLimitException) {
            throw new AssistantException('The AI assistant is busy right now. Please try again in a minute.');
        } catch (APIStatusException|APIConnectionException $e) {
            report($e);

            throw new AssistantException('The AI assistant could not be reached. Please try again.');
        }

        if ($message->stopReason === 'refusal') {
            throw new AssistantException('The AI assistant could not analyse this lead.');
        }

        $insight = $message->parsedOutput();

        if (! $insight instanceof LeadInsight) {
            throw new AssistantException('The AI assistant returned an unexpected answer. Please try again.');
        }

        return $insight;
    }
}
