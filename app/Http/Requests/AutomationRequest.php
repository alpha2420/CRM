<?php

namespace App\Http\Requests;

use App\Enums\AutomationTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AutomationRequest extends FormRequest
{
    private const ACTIONS = ['assign_to', 'set_status_id', 'whatsapp_template_id', 'follow_up_in_hours', 'notify_user_id', 'start_sequence_id'];

    public function rules(): array
    {
        $org = $this->user()->organization_id;
        $user = Rule::exists('users', 'id')->where('organization_id', $org)->where('is_active', true);
        $status = Rule::exists('lead_statuses', 'id')->where('organization_id', $org);

        return [
            'name' => ['required', 'string', 'max:100'],
            'trigger' => ['required', Rule::enum(AutomationTrigger::class)],
            'conditions.source_id' => ['nullable', Rule::exists('sources', 'id')->where('organization_id', $org)],
            'conditions.status_id' => ['nullable', $status],
            'actions.assign_to' => ['nullable', $user],
            'actions.set_status_id' => ['nullable', $status],
            'actions.whatsapp_template_id' => ['nullable', Rule::exists('whatsapp_templates', 'id')->where('organization_id', $org)],
            'actions.follow_up_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'actions.notify_user_id' => ['nullable', $user],
            'actions.start_sequence_id' => ['nullable', Rule::exists('sequences', 'id')->where('organization_id', $org)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (collect(self::ACTIONS)->every(fn ($key) => blank($this->input("actions.{$key}")))) {
                $validator->errors()->add('actions', 'Choose at least one thing for the automation to do.');
            }
        }];
    }

    /**
     * @return array{name: string, trigger: string, conditions: array<string, int>, actions: array<string, int>}
     */
    public function automation(): array
    {
        $clean = fn (array $values) => array_map('intval', array_filter($values, fn ($value) => filled($value)));

        return [
            'name' => $this->validated('name'),
            'trigger' => $this->validated('trigger'),
            'conditions' => $clean((array) $this->validated('conditions', [])),
            'actions' => $clean((array) $this->validated('actions', [])),
        ];
    }
}
