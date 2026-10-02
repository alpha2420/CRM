<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoutingRuleRequest extends FormRequest
{
    public function rules(): array
    {
        $org = $this->user()->organization_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'conditions.source_id' => ['nullable', Rule::exists('sources', 'id')->where('organization_id', $org)],
            'conditions.city' => ['nullable', 'string', 'max:100'],
            'conditions.field_key' => ['nullable', Rule::exists('custom_fields', 'key')->where('organization_id', $org)],
            'conditions.field_value' => ['nullable', 'string', 'max:150', 'required_with:conditions.field_key'],
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => ['integer', Rule::exists('users', 'id')->where('organization_id', $org)->where('is_active', true)],
        ];
    }

    public function messages(): array
    {
        return [
            'agent_ids.required' => 'Choose who gets these leads.',
            'conditions.field_value.required_with' => 'Say which value the field must have.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->conditions() === []) {
                $validator->errors()->add('conditions', 'Set at least one condition, otherwise every lead would match.');
            }
        }];
    }

    /**
     * @return array{name: string, conditions: array<string, int|string>, agent_ids: list<int>}
     */
    public function rule(): array
    {
        return [
            'name' => $this->validated('name'),
            'conditions' => $this->conditions(),
            'agent_ids' => array_values(array_unique(array_map('intval', (array) $this->validated('agent_ids')))),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function conditions(): array
    {
        $input = (array) $this->input('conditions', []);
        $conditions = array_filter([
            'source_id' => filled($input['source_id'] ?? null) ? (int) $input['source_id'] : null,
            'city' => filled($input['city'] ?? null) ? trim((string) $input['city']) : null,
            'field_key' => filled($input['field_key'] ?? null) ? (string) $input['field_key'] : null,
        ], fn ($value) => $value !== null);

        if (isset($conditions['field_key'])) {
            $conditions['field_value'] = trim((string) ($input['field_value'] ?? ''));
        }

        return $conditions;
    }
}
