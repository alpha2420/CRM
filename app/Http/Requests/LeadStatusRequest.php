<?php

namespace App\Http\Requests;

use App\Enums\StatusType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('lead_statuses', 'name')
                    ->where('organization_id', $this->user()->organization_id)
                    ->ignore($this->route('status')),
            ],
            'type' => ['required', Rule::enum(StatusType::class)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
