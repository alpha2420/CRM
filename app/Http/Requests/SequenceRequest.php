<?php

namespace App\Http\Requests;

use App\Enums\SequenceStepAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SequenceRequest extends FormRequest
{
    public const MAX_STEPS = 10;

    public function rules(): array
    {
        $template = Rule::exists('whatsapp_templates', 'id')->where('organization_id', $this->user()->organization_id);

        return [
            'name' => ['required', 'string', 'max:100'],
            'steps' => ['array', 'max:'.self::MAX_STEPS],
            'steps.*.action' => ['nullable', Rule::enum(SequenceStepAction::class)],
            'steps.*.day' => ['nullable', 'integer', 'min:0', 'max:90', 'required_with:steps.*.action'],
            'steps.*.whatsapp_template_id' => ['nullable', $template, 'required_if:steps.*.action,'.SequenceStepAction::WhatsAppTemplate->value],
            'steps.*.note' => ['nullable', 'string', 'max:200', 'required_if:steps.*.action,'.SequenceStepAction::RemindOwner->value],
        ];
    }

    public function messages(): array
    {
        return [
            'steps.*.whatsapp_template_id.required_if' => 'Pick the WhatsApp template for each message step.',
            'steps.*.note.required_if' => 'Say what the owner should do for each reminder step.',
            'steps.*.day.required_with' => 'Give every step a day.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->steps() === []) {
                $validator->errors()->add('steps', 'Add at least one step.');
            }
        }];
    }

    /**
     * @return array{name: string, stop_on_reply: bool}
     */
    public function sequence(): array
    {
        return ['name' => $this->validated('name'), 'stop_on_reply' => $this->boolean('stop_on_reply')];
    }

    /**
     * Filled-in rows only, in day order, keeping just the field each action uses.
     *
     * @return list<array{day: int, action: string, whatsapp_template_id: ?int, note: ?string}>
     */
    public function steps(): array
    {
        return collect((array) $this->input('steps', []))
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->filter(fn (array $row) => SequenceStepAction::tryFrom((string) ($row['action'] ?? '')) !== null)
            ->map(function (array $row) {
                $action = SequenceStepAction::from($row['action']);

                return [
                    'day' => (int) ($row['day'] ?? 0),
                    'action' => $action->value,
                    'whatsapp_template_id' => $action === SequenceStepAction::WhatsAppTemplate ? ((int) ($row['whatsapp_template_id'] ?? 0) ?: null) : null,
                    'note' => $action === SequenceStepAction::RemindOwner ? trim((string) ($row['note'] ?? '')) : null,
                ];
            })
            ->sortBy('day')
            ->values()
            ->all();
    }
}
