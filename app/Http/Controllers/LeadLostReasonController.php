<?php

namespace App\Http\Controllers;

use App\Enums\StatusType;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Say why a lost lead was lost, after the fact (for leads closed from the
 * board, in bulk or by an automation).
 */
class LeadLostReasonController extends Controller
{
    public function __invoke(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);
        abort_unless($lead->loadMissing('status')->status->type === StatusType::Lost, 422, 'Only lost leads have a lost reason.');

        $data = $request->validate([
            'lost_reason_id' => ['required', Rule::exists('lost_reasons', 'id')->where('organization_id', $lead->organization_id)],
        ]);
        $lead->forceFill(['lost_reason_id' => (int) $data['lost_reason_id']])->save();

        return back()->with('status', 'Lost reason saved.');
    }
}
