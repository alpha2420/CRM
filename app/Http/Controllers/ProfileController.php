<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Security\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileRequest $request, SessionManager $sessions): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));

        $passwordChanged = $request->filled('password');
        if ($passwordChanged) {
            $user->password = $request->string('password');
        }

        $user->save();

        // A new password signs out every other device.
        if ($passwordChanged) {
            $sessions->endOthers($request);
        }

        return redirect()->route('profile.edit')->with('status', 'Profile updated.');
    }
}
