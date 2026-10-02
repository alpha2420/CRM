<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\CustomField;
use App\Models\Lead;
use App\Support\LocalTime;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Create and update a lead. Every foreign key is checked against the
 * user's own organization, so ids from another tenant are rejected.
 */
class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead ? $this->user()->can('update', $lead) : true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => PhoneNumber::international((string) $this->input('phone'), $this->user()->organization->countryCode())]);
        }
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;
        $lead = $this->route('lead');

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required', PhoneNumber::RULE,
                Rule::unique('leads', 'phone')->where('organization_id', $organizationId)->ignore($lead),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', Rule::exists('sources', 'id')->where('organization_id', $organizationId)],
            'status_id' => [$lead ? 'required' : 'nullable', Rule::exists('lead_statuses', 'id')->where('organization_id', $organizationId)],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ];

        // Only admins choose the owner; an agent's leads are always their own.
        if ($this->user()->isAdmin()) {
            $rules['assigned_to'] = ['nullable', Rule::exists('users', 'id')->where('organization_id', $organizationId)];
        }

        foreach ($this->customFields() as $field) {
            $rules["custom.{$field->key}"] = $field->rules();
        }

        return $rules;
    }

    public function attributes(): array
    {
        return $this->customFields()
            ->mapWithKeys(fn (CustomField $field) => ["custom.{$field->key}" => $field->label])
            ->all();
    }

    /**
     * Validated lead attributes, with custom field answers folded into
     * "custom_values".
     *
     * @return array<string, mixed>
     */
    public function leadData(): array
    {
        $data = $this->safe()->except('custom');

        if (array_key_exists('next_follow_up_at', $data)) {
            $data['next_follow_up_at'] = LocalTime::toUtc($data['next_follow_up_at']);
        }

        $data['custom_values'] = $this->customFields()
            ->mapWithKeys(fn (CustomField $field) => [$field->key => $this->validated("custom.{$field->key}")])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all() ?: null;

        return $data;
    }

    /**
     * @return Collection<int, CustomField>
     */
    private function customFields()
    {
        return once(fn () => CustomField::query()->ordered()->get());
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'A lead with this phone number already exists.',
            'phone.regex' => 'Enter a valid phone number (5-20 digits, optional leading +).',
        ];
    }
}
