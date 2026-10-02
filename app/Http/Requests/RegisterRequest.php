<?php

namespace App\Http\Requests;

use App\Support\TimeZoneName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * The time zone comes from the browser, not from the person: an old
     * name is updated and an unknown one dropped (the workspace then starts
     * on the default zone), so it can never block a sign-up.
     */
    protected function prepareForValidation(): void
    {
        $timezone = $this->input('timezone');

        $this->merge(['timezone' => is_string($timezone) ? TimeZoneName::current($timezone) : null]);
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'timezone' => ['nullable', 'timezone:all'],
        ];
    }
}
