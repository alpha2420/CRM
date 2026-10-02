<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Sequence;
use App\Sequences\SequenceEnroller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Start or stop a follow-up sequence for one lead.
 */
class LeadSequenceController extends Controller
{
    public function store(Request $request, Lead $lead, SequenceEnroller $enroller): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'sequence_id' => ['required', Rule::exists('sequences', 'id')->where('organization_id', $lead->organization_id)->where('is_active', true)],
        ]);
        $sequence = Sequence::query()->findOrFail($data['sequence_id']);

        $enroller->enroll($lead, $sequence, $request->user());

        return back()->with('status', "Started “{$sequence->name}”.");
    }

    public function destroy(Request $request, Lead $lead, SequenceEnroller $enroller): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $enroller->stop($lead, "stopped by {$request->user()->name}");

        return back()->with('status', 'Sequence stopped.');
    }
}
