<?php

namespace App\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Google Gemini over its REST API (generateContent), asked to answer in
 * JSON that matches LeadInsight. A temporary failure gets one more try;
 * if the model stays overloaded or rate-limited, the fallback model is
 * asked instead.
 */
final class GeminiInsightGenerator implements InsightGenerator
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Rate-limited or overloaded: worth trying another model. */
    private const BUSY = [429, 503];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $fallbackModel = null,
    ) {}

    public function generate(string $system, string $prompt): LeadInsight
    {
        foreach (array_unique(array_filter([$this->model, $this->fallbackModel])) as $model) {
            $response = $this->ask($model, $system, $prompt);

            if (! in_array($response->status(), self::BUSY, true)) {
                return $this->insightFrom($response);
            }
        }

        throw new AssistantException('The AI assistant is busy right now. Please try again in a minute.');
    }

    private function ask(string $model, string $system, string $prompt): Response
    {
        try {
            return Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->timeout(60)
                ->retry(2, 1000, fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false)
                ->post(sprintf(self::ENDPOINT, rawurlencode($model)), [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => LeadInsight::schema(),
                        'temperature' => 0.4,
                        'maxOutputTokens' => 8192,
                    ],
                ]);
        } catch (ConnectionException) {
            throw new AssistantException('The AI assistant could not be reached. Please try again.');
        }
    }

    private function insightFrom(Response $response): LeadInsight
    {
        if ($response->failed()) {
            report(new RuntimeException("Gemini returned {$response->status()}: ".$response->json('error.message', 'no details')));

            throw new AssistantException('The AI assistant could not be reached. Please try again.');
        }

        // Thinking models may add "thought" parts; the answer is the rest.
        $text = collect($response->json('candidates.0.content.parts', []))
            ->reject(fn (array $part) => $part['thought'] ?? false)
            ->pluck('text')
            ->implode('');
        $answer = json_decode($text, true);

        if (! is_array($answer) || ! LeadInsight::isComplete($answer)) {
            // Empty when the request was blocked; otherwise cut short or malformed.
            throw new AssistantException('The AI assistant could not analyse this lead.');
        }

        return LeadInsight::fromArray($answer);
    }
}
