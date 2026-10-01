<?php

namespace App\Http\Controllers;

use App\Billing\PlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public marketing page. Signed-in users go straight to their workspace.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request, PlanCatalog $plans): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('landing', [
            'plans' => $plans->paid(),
            'trialDays' => (int) config('plans.trial_days'),
        ]);
    }
}
