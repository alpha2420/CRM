<?php

namespace App\Support;

/**
 * Turns the free-form answers of ad lead forms into lead attributes.
 * Known questions become fields; every other answer is kept in notes.
 */
final class LeadFieldMapper
{
    private const ALIASES = [
        'fullname' => 'name', 'name' => 'name',
        'firstname' => 'first_name', 'lastname' => 'last_name',
        'phonenumber' => 'phone', 'phone' => 'phone', 'mobile' => 'phone', 'mobilenumber' => 'phone', 'whatsappnumber' => 'phone',
        'email' => 'email', 'workemail' => 'email',
        'city' => 'city',
        'companyname' => 'company', 'company' => 'company',
    ];

    /**
     * Facebook / Instagram: [{"name": "full_name", "values": ["Jane"]}, ...]
     *
     * @param  list<array{name: string, values: list<string>}>  $fieldData
     * @return array<string, ?string>
     */
    public static function fromFacebook(array $fieldData): array
    {
        $answers = [];
        foreach ($fieldData as $field) {
            $answers[(string) ($field['name'] ?? '')] = implode(', ', (array) ($field['values'] ?? []));
        }

        return self::map($answers);
    }

    /**
     * Google Ads: [{"column_id": "FULL_NAME", "column_name": "Full Name", "string_value": "Jane"}, ...]
     *
     * @param  list<array<string, string>>  $columns
     * @return array<string, ?string>
     */
    public static function fromGoogle(array $columns): array
    {
        $answers = [];
        foreach ($columns as $column) {
            $key = $column['column_id'] ?? $column['column_name'] ?? '';
            $answers[(string) $key] = (string) ($column['string_value'] ?? '');
        }

        return self::map($answers);
    }

    /**
     * @param  array<string, string>  $answers
     * @return array<string, ?string>
     */
    private static function map(array $answers): array
    {
        $lead = [];
        $notes = [];

        foreach ($answers as $question => $answer) {
            $field = self::ALIASES[strtolower(preg_replace('/[^a-z0-9]/i', '', $question))] ?? null;

            if ($answer === '') {
                continue;
            }

            if ($field === null) {
                $notes[] = ucfirst(str_replace('_', ' ', strtolower($question))).': '.$answer;
            } else {
                $lead[$field] ??= $answer;
            }
        }

        $name = $lead['name'] ?? trim(($lead['first_name'] ?? '').' '.($lead['last_name'] ?? ''));

        return [
            'name' => $name !== '' ? $name : null,
            'phone' => $lead['phone'] ?? null,
            'email' => $lead['email'] ?? null,
            'city' => $lead['city'] ?? null,
            'company' => $lead['company'] ?? null,
            'notes' => $notes ? implode("\n", $notes) : null,
        ];
    }
}
