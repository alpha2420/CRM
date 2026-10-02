<?php

namespace App\Ai;

/**
 * Which AI service powers the assistant (AI_PROVIDER in .env). Each one
 * has its own key and model under config('services.<provider>').
 */
enum AiProvider: string
{
    case Gemini = 'gemini';
    case Anthropic = 'anthropic';

    public static function current(): self
    {
        return self::tryFrom((string) config('services.ai.provider')) ?? self::Gemini;
    }

    public function apiKey(): string
    {
        return (string) config("services.{$this->value}.api_key");
    }

    public function model(): string
    {
        return (string) config("services.{$this->value}.model");
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /** The company that processes lead data, as named in the privacy policy. */
    public function company(): string
    {
        return match ($this) {
            self::Gemini => 'Google (Gemini)',
            self::Anthropic => 'Anthropic (Claude)',
        };
    }

    public function envKey(): string
    {
        return match ($this) {
            self::Gemini => 'GEMINI_API_KEY',
            self::Anthropic => 'ANTHROPIC_API_KEY',
        };
    }
}
