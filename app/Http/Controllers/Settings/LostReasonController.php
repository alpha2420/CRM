<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\LostReasonRequest;
use App\Models\LostReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LostReasonController extends Controller
{
    public function index(): View
    {
        return view('settings.lost-reasons', [
            'reasons' => LostReason::query()->ordered()->withCount('leads')->get(),
        ]);
    }

    public function store(LostReasonRequest $request): RedirectResponse
    {
        LostReason::create($request->reason() + ['sort_order' => (int) LostReason::query()->max('sort_order') + 1]);

        return back()->with('status', 'Reason added.');
    }

    public function update(LostReasonRequest $request, LostReason $lostReason): RedirectResponse
    {
        $lostReason->update($request->reason());

        return back()->with('status', 'Reason updated.');
    }

    public function destroy(LostReason $lostReason): RedirectResponse
    {
        $lostReason->delete();

        return back()->with('status', 'Reason deleted. Leads that had it keep no reason.');
    }
}
