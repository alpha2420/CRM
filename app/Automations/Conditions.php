<?php

namespace App\Automations;

use App\Models\Lead;
use Closure;

/**
 * "Only if" checks shared by automations and routing rules. Each kind of
 * condition is one small check; every condition that is set must hold.
 *
 * Keys: source_id, status_id, priority, min_value, city, field_key +
 * field_value, keywords (a WhatsApp message containing any of them).
 */
final readonly class Conditions
{
    /**
     * @param  array<string, mixed>  $conditions
     */
    public function __construct(private array $conditions) {}

    /**
     * @param  array{message?: string}  $context  what happened (e.g. the message text)
     */
    public function matches(Lead $lead, array $context = []): bool
    {
        foreach ($this->checks() as $key => $check) {
            $expected = $this->conditions[$key] ?? null;

            if (filled($expected) && ! $check($expected, $lead, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * "price, cost , Rate" → ["price", "cost", "rate"]
     *
     * @return list<string>
     */
    public static function keywords(string $words): array
    {
        return array_values(array_filter(array_map(fn (string $word) => mb_strtolower(trim($word)), explode(',', $words))));
    }

    /**
     * @return array<string, Closure(mixed, Lead, array{message?: string}): bool>
     */
    private function checks(): array
    {
        $same = fn (mixed $a, mixed $b) => strcasecmp(trim((string) $a), trim((string) $b)) === 0;

        return [
            'source_id' => fn (mixed $id, Lead $lead) => (int) $id === (int) $lead->source_id,
            'status_id' => fn (mixed $id, Lead $lead) => (int) $id === (int) $lead->status_id,
            'priority' => fn (mixed $priority, Lead $lead) => $lead->priority->value === $priority,
            'min_value' => fn (mixed $minimum, Lead $lead) => $lead->value !== null && (float) $lead->value >= (float) $minimum,
            'city' => fn (mixed $city, Lead $lead) => $same($city, $lead->city),
            'field_key' => fn (mixed $key, Lead $lead) => $same($this->conditions['field_value'] ?? '', $lead->custom_values[$key] ?? ''),
            'keywords' => function (mixed $words, Lead $lead, array $context) {
                $message = mb_strtolower((string) ($context['message'] ?? ''));

                return collect(self::keywords((string) $words))->contains(fn (string $word) => str_contains($message, $word));
            },
        ];
    }
}
