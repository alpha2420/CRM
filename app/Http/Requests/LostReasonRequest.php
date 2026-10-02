<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LostReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'win_back_after_days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ];
    }

    /**
     * @return array{name: string, win_back_after_days: ?int}
     */
    public function reason(): array
    {
        return [
            'name' => trim($this->validated('name')),
            'win_back_after_days' => $this->validated('win_back_after_days') ? (int) $this->validated('win_back_after_days') : null,
        ];
    }
}
