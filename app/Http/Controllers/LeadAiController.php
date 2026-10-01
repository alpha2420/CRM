<?php

namespace App\Http\Controllers;

use App\Ai\AssistantException;
use App\Ai\LeadAssistant;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LeadAiController extends Controller
{
    public function __invoke(Lead $lead, LeadAssistant $assistant): RedirectResponse
    {
        Gate::authorize('view', $lead);

        try {
            $assistant->analyse($lead);
        } catch (AssistantException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('status', 'AI insight updated.');
    }
}
