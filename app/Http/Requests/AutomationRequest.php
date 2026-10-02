<?php

namespace App\Http\Requests;

use App\Enums\AutomationTrigger;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AutomationRequest extends FormRequest
{
    /** Condition and action keys with how each value is stored. */
    private const CONDITIONS = [
        'source_id' => 'int', 'status_id' => 'int', 'priority' => 'string', 'min_value' => 'int',
        'city' => 'string', 'field_key' => 'string', 'field_value' => 'string', 'keywords' => 'string',
    ];

    private const ACTIONS = [
        'assign_to' => 'int', 'set_status_id' => 'int', 'set_priority' => 'string', 'whatsapp_template_id' => 'int',
        'follow_up_in_hours' => 'int', 'notify_user_id' => 'int', 'start_sequence_id' => 'int',
    ];

    public function rules(): array
    {
        $org = $this->user()->organization_id;
        $user = Rule::exists('users', 'id')->where('organization_id', $org)->where('is_active', true);
        $status = Rule::exists('lead_statuses', 'id')->where('organization_id', $org);
        $scheduled = array_map(fn (AutomationTrigger $t) => $t->value, array_filter(AutomationTrigger::cases(), fn (AutomationTrigger $t) => $t->isScheduled()));

        return [
            'name' => ['required', 'string', 'max:100'],
            'trigger' => ['required', Rule::enum(AutomationTrigger::class)],
            'trigger_after' => ['nullable', 'integer', 'min:1', 'max:365', Rule::requiredIf(in_array($this->input('trigger'), $scheduled, true))],
            'conditions.source_id' => ['nullable', Rule::exists('sources', 'id')->where('organization_id', $org)],
            'conditions.status_id' => ['nullable', $status],
            'conditions.priority' => ['nullable', Rule::enum(Priority::class)],
            'conditions.min_value' => ['nullable', 'integer', 'min:1'],
            'conditions.city' => ['nullable', 'string', 'max:100'],
            'conditions.field_key' => ['nullable', Rule::exists('custom_fields', 'key')->where('organization_id', $org)],
            'conditions.field_value' => ['nullable', 'string', 'max:150', 'required_with:conditions.field_key'],
            'conditions.keywords' => ['nullable', 'string', 'max:200'],
            'actions.assign_to' => ['nullable', $user],
            'actions.set_status_id' => ['nullable', $status],
            'actions.set_priority' => ['nullable', Rule::enum(Priority::class)],
            'actions.whatsapp_template_id' => ['nullable', Rule::exists('whatsapp_templates', 'id')->where('organization_id', $org)],
            'actions.follow_up_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'actions.notify_user_id' => ['nullable', $user],
            'actions.start_sequence_id' => ['nullable', Rule::exists('sequences', 'id')->where('organization_id', $org)],
        ];
    }

    public function messages(): array
    {
        return ['trigger_after.required' => 'Say how long to wait before this rule runs.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->clean('actions', self::ACTIONS) === []) {
                $validator->errors()->add('actions', 'Choose at least one thing for the automation to do.');
            }
        }];
    }

    /**
     * @return array{name: string, trigger: string, trigger_after: ?int, conditions: array<string, int|string>, actions: array<string, int|string>}
     */
    public function automation(): array
    {
        $trigger = AutomationTrigger::from($this->validated('trigger'));

        return [
            'name' => $this->validated('name'),
            'trigger' => $trigger->value,
            'trigger_after' => $trigger->isScheduled() ? (int) $this->validated('trigger_after') : null,
            'conditions' => $this->clean('conditions', self::CONDITIONS),
            'actions' => $this->clean('actions', self::ACTIONS),
        ];
    }

    /**
     * Filled-in values only, each stored as its type.
     *
     * @param  array<string, string>  $types
     * @return array<string, int|string>
     */
    private function clean(string $group, array $types): array
    {
        $values = [];
        foreach ($types as $key => $type) {
            $value = $this->input("{$group}.{$key}");
            if (filled($value)) {
                $values[$key] = $type === 'int' ? (int) $value : trim((string) $value);
            }
        }

        return $values;
    }
}
