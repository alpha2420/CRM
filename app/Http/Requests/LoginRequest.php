<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Deactivated users get the same message as a wrong password, so the
     * form does not reveal which accounts exist.
     */
    public function authenticate(): void
    {
        $credentials = $this->only('email', 'password') + ['is_active' => true];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
    }
}
