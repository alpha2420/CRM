<?php

namespace App\Ai;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;
use ReflectionClass;
use ReflectionProperty;

/**
 * The shape the AI returns for a lead. The descriptions below are the
 * single source for both providers: Claude reads them through the SDK's
 * structured outputs, Gemini through schema().
 */
class LeadInsight implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    public const TEMPERATURES = ['hot', 'warm', 'cold'];

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
            'temperature' => in_array($temperature, self::TEMPERATURES, true) ? $temperature : 'warm',
            'reason' => trim($this->reason),
            'next_step' => trim($this->next_step),
            'suggested_message' => trim($this->suggested_message),
        ];
    }

    /**
     * The answer as a Gemini response schema (an OpenAPI-style subset).
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $properties = [];
        foreach (self::fields() as $name => $description) {
            $properties[$name] = ['type' => 'STRING', 'description' => $description]
                + ($name === 'temperature' ? ['format' => 'enum', 'enum' => self::TEMPERATURES] : []);
        }

        return [
            'type' => 'OBJECT',
            'properties' => $properties,
            'required' => array_keys($properties),
            'propertyOrdering' => array_keys($properties),
        ];
    }

    /**
     * Every field present and non-empty: safe to pass to fromArray()
     * (provided by the SDK trait).
     *
     * @param  array<array-key, mixed>  $answer
     */
    public static function isComplete(array $answer): bool
    {
        foreach (array_keys(self::fields()) as $name) {
            if (! is_string($answer[$name] ?? null) || trim($answer[$name]) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Field name => description, read from the #[Constrained] attributes.
     *
     * @return array<string, string>
     */
    private static function fields(): array
    {
        $fields = [];
        foreach ((new ReflectionClass(self::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $constraint = $property->getAttributes(Constrained::class)[0] ?? null;
            if ($constraint !== null) {
                $fields[$property->getName()] = (string) $constraint->newInstance()->description;
            }
        }

        return $fields;
    }
}
