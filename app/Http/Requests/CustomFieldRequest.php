<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomFieldRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $options = collect(preg_split('/[,\n]/', (string) $this->input('options')))
            ->map(fn (string $option) => trim($option))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'options' => $options,
            'is_required' => $this->boolean('is_required'),
            // The key is fixed at creation so renaming a label keeps the data.
            'key' => $this->route('custom_field')?->key ?? Str::slug((string) $this->input('label'), '_'),
        ]);
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:60'],
            'key' => [
                'required', 'string', 'max:60',
                Rule::notIn(['name', 'phone', 'email', 'company', 'city', 'source', 'status', 'value', 'priority', 'notes', 'assigned_to']),
                Rule::unique('custom_fields', 'key')
                    ->where('organization_id', $this->user()->organization_id)
                    ->ignore($this->route('custom_field')),
            ],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['array', Rule::requiredIf($this->input('type') === CustomFieldType::Select->value), 'max:50'],
            'options.*' => ['string', 'max:60'],
            'is_required' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.unique' => 'A field with this name already exists.',
            'key.not_in' => 'That name is already a built-in lead field.',
            'options.required' => 'List the choices for a dropdown, separated by commas.',
        ];
    }
}
