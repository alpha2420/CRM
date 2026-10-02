<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Change a lead's stage in one step: dragging a card on the board, clicking
 * the stage bar on the lead page or picking a stage in the inbox.
 */
class LeadMoveController extends Controller
{
    public function __invoke(Request $request, Lead $lead, LeadService $leads): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'status_id' => ['required', 'integer', Rule::exists('lead_statuses', 'id')->where('organization_id', $request->user()->organization_id)],
            'lost_reason_id' => ['nullable', 'integer', Rule::exists('lost_reasons', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);

        $leads->changeStatus($lead, $request->user(), (int) $data['status_id'], isset($data['lost_reason_id']) ? (int) $data['lost_reason_id'] : null);
        $status = $lead->fresh('status')->status;

        if ($request->expectsJson()) {
            return response()->json(['status' => ['id' => $status->id, 'name' => $status->name]]);
        }

        return back()->with('status', "Moved to {$status->name}.");
    }
}
