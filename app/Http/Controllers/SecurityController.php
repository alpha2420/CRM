<?php

namespace App\Http\Controllers;

use App\Security\SessionManager;
use App\Security\TwoFactor;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's own security: two-factor login and active sessions.
 */
class SecurityController extends Controller
{
    public function show(Request $request, TwoFactor $twoFactor, SessionManager $sessions): View
    {
        $user = $request->user();
        $settingUp = $user->two_factor_secret !== null && ! $user->hasTwoFactor();

        return view('profile.security', [
            'user' => $user,
            'settingUp' => $settingUp,
            'qrCode' => $settingUp ? $twoFactor->qrCodeSvg($user) : null,
            'sessions' => $sessions->for($request),
            'required' => $user->organization->require_two_factor,
        ]);
    }

    public function enable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $twoFactor->begin($request->user());

        return redirect()->route('security.show');
    }

    public function confirm(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $codes = $twoFactor->confirm($request->user(), $request->string('code')->toString());

        if ($codes === null) {
            return back()->withErrors(['code' => 'That code is not valid. Check your phone\'s clock and try the newest code.']);
        }

        app(AuditLogger::class)->log('security.2fa_enabled', 'Turned on two-factor login');

        return redirect()->route('security.show')
            ->with('status', 'Two-factor login is on.')
            ->with('recovery_codes', $codes);
    }

    public function recoveryCodes(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        app(AuditLogger::class)->log('security.recovery_codes', 'Generated new two-factor recovery codes');

        return back()->with('recovery_codes', $twoFactor->regenerateRecoveryCodes($request->user()));
    }

    public function disable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if ($request->user()->organization->require_two_factor) {
            return back()->withErrors(['password' => 'Your workspace requires two-factor login, so it can\'t be turned off.']);
        }

        $twoFactor->disable($request->user());
        app(AuditLogger::class)->log('security.2fa_disabled', 'Turned off two-factor login');

        return back()->with('status', 'Two-factor login is off.');
    }

    public function logoutOthers(Request $request, SessionManager $sessions): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $count = $sessions->endOthers($request);
        app(AuditLogger::class)->log('security.sessions_ended', "Signed out {$count} other ".str('device')->plural($count));

        return back()->with('status', $count ? "Signed out of {$count} other ".str('device')->plural($count).'.' : 'No other devices were signed in.');
    }
}
