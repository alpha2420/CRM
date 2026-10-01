<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadActivityRequest;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;

class LeadActivityController extends Controller
{
    public function store(LeadActivityRequest $request, Lead $lead, LeadService $leads): RedirectResponse
    {
        $leads->logActivity($lead, $request->user(), $request->validated());

        return redirect()->route('leads.show', $lead)->with('status', 'Follow-up saved.');
    }
}
