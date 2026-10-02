<?php

namespace App\Http\Requests;

use App\Models\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BroadcastRequest extends FormRequest
{
    public function rules(): array
    {
        $org = $this->user()->organization_id;
        $preview = $this->has('preview');

        return [
            'name' => [$preview ? 'nullable' : 'required', 'string', 'max:100'],
            'whatsapp_template_id' => [$preview ? 'nullable' : 'required', Rule::exists('whatsapp_templates', 'id')->where('organization_id', $org)->where('status', 'APPROVED')],
            'values' => ['array'],
            'values.*' => ['nullable', 'string', 'max:200'],
            'audience.stage' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) use ($org) {
                $known = in_array($value, ['all', 'open', 'won'], true)
                    || (is_numeric($value) && LeadStatus::query()->where('organization_id', $org)->whereKey((int) $value)->exists());
                if (! $known) {
                    $fail('Choose who to send to.');
                }
            }],
            'audience.source_id' => ['nullable', Rule::exists('sources', 'id')->where('organization_id', $org)],
            'audience.campaign' => ['nullable', 'string', 'max:150'],
            'audience.assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'confirm' => [$preview ? 'nullable' : 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Tick the box to confirm you want to send this.',
            'whatsapp_template_id.required' => 'Choose an approved template.',
        ];
    }

    /**
     * @return array{stage: string, source_id: ?int, campaign: ?string, assigned_to: ?int}
     */
    public function audience(): array
    {
        $audience = (array) $this->validated('audience', []);

        return [
            'stage' => (string) ($audience['stage'] ?? 'open'),
            'source_id' => isset($audience['source_id']) ? (int) $audience['source_id'] : null,
            'campaign' => filled($audience['campaign'] ?? null) ? trim((string) $audience['campaign']) : null,
            'assigned_to' => isset($audience['assigned_to']) ? (int) $audience['assigned_to'] : null,
        ];
    }
}
