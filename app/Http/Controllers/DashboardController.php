<?php

namespace App\Http\Controllers;

use App\Services\DashboardStats;
use App\Services\OnboardingChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats, OnboardingChecklist $onboarding): View
    {
        return view('dashboard', [
            'stats' => $stats->for($request->user()),
            'onboarding' => $onboarding->for($request->user()),
        ]);
    }

    public function dismissOnboarding(Request $request): RedirectResponse
    {
        Gate::authorize('admin');

        $request->user()->organization->forceFill(['onboarding_dismissed_at' => now()])->save();

        return back();
    }
}
