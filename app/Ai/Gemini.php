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
 * JSON that matches a schema. A temporary failure gets one more try; if
 * the model stays overloaded or rate-limited, the fallback model is asked
 * instead. Shared by everything in the CRM that uses Gemini.
 */
final class Gemini
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Rate-limited or overloaded: worth trying another model. */
    private const BUSY = [429, 503];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $fallbackModel = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.gemini.api_key'),
            (string) config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Ask for an answer shaped like $schema.
     *
     * @param  list<array<string, mixed>>  $parts  the user turn: text and/or inline files
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null the decoded answer; null when it was blocked or malformed
     *
     * @throws AssistantException when Gemini is busy or cannot be reached
     */
    public function json(string $system, array $parts, array $schema, float $temperature = 0.4): ?array
    {
        foreach (array_unique(array_filter([$this->model, $this->fallbackModel])) as $model) {
            $response = $this->ask($model, $system, $parts, $schema, $temperature);

            if (! in_array($response->status(), self::BUSY, true)) {
                return $this->answerFrom($response);
            }
        }

        throw new AssistantException('The AI assistant is busy right now. Please try again in a minute.');
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @param  array<string, mixed>  $schema
     */
    private function ask(string $model, string $system, array $parts, array $schema, float $temperature): Response
    {
        try {
            return Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->timeout(60)
                ->retry(2, 1000, fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false)
                ->post(sprintf(self::ENDPOINT, rawurlencode($model)), [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $schema,
                        'temperature' => $temperature,
                        'maxOutputTokens' => 8192,
                    ],
                ]);
        } catch (ConnectionException) {
            throw new AssistantException('The AI assistant could not be reached. Please try again.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function answerFrom(Response $response): ?array
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

        // Empty when the request was blocked; otherwise cut short or malformed.
        return is_array($answer) ? $answer : null;
    }
}
