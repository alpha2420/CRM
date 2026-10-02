<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "I'm away" / "I'm back": while away, new leads skip you.
 */
class AvailabilityController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['is_available' => ! $user->is_available])->save();

        return back()->with('status', $user->is_available ? 'Welcome back: you will get new leads again.' : 'You are away: new leads go to the rest of the team.');
    }
}
