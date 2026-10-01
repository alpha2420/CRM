<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second step of login for users with two-factor turned on.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        return $request->session()->has('login.id')
            ? view('auth.two-factor-challenge')
            : redirect()->route('login');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = User::find($request->session()->get('login.id'));

        if ($user === null || ! $user->is_active || ! $user->hasTwoFactor()) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login');
        }

        if (! $twoFactor->check($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Try the latest code from your app, or a recovery code.']);
        }

        Auth::login($user, (bool) $request->session()->pull('login.remember'));
        $request->session()->forget('login.id');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
