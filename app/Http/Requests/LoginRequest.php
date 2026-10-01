<?php

namespace App\Http\Requests;

use App\Models\User;
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
     * Check the email and password and return the user, without signing
     * them in yet (two-factor may still be needed). Deactivated users get
     * the same message as a wrong password, so the form does not reveal
     * which accounts exist.
     */
    public function validatedUser(): User
    {
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials(['email' => $this->string('email')->toString()]);

        if (! $user instanceof User || ! $user->is_active || ! $provider->validateCredentials($user, ['password' => $this->string('password')->toString()])) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return $user;
    }
}
