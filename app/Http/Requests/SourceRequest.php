<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sources', 'name')
                    ->where('organization_id', $this->user()->organization_id)
                    ->ignore($this->route('source')),
            ],
        ];
    }
}
