<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\OrganizationRegistrar;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, OrganizationRegistrar $registrar): RedirectResponse
    {
        $user = $registrar->register(
            $request->string('organization_name'),
            $request->string('name'),
            $request->string('email'),
            $request->string('password'),
            $request->input('timezone'),
        );

        // With verification off, the address is accepted as given, so no
        // verification email is sent (and turning it on later won't lock
        // these users out).
        if (! config('crm.require_email_verification')) {
            $user->markEmailAsVerified();
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Your workspace is ready. Your free trial has started.');
    }
}
