<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Apply one change to many selected leads. Each lead is saved through the
 * model, so events (notifications, automations, history) still fire, and
 * each is authorized individually.
 */
class LeadBulkController extends Controller
{
    private const MAX = 500;

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        // The bar sends one value such as "status:4", "assign:7" or "delete".
        [$action, $target] = array_pad(explode(':', (string) $request->input('operation'), 2), 2, null);
        $request->merge([
            'action' => $action,
            'status_id' => $action === 'status' ? $target : null,
            'assigned_to' => $action === 'assign' ? $target : null,
        ]);

        $data = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in($user->isAdmin() ? ['status', 'assign', 'delete'] : ['status'])],
            'status_id' => ['required_if:action,status', 'nullable', Rule::exists('lead_statuses', 'id')->where('organization_id', $organizationId)],
            'assigned_to' => ['required_if:action,assign', 'nullable', Rule::exists('users', 'id')->where('organization_id', $organizationId)->where('is_active', true)],
        ], ['ids.required' => 'Select at least one lead first.', 'action.required' => 'Choose what to do with the selected leads.']);

        $leads = Lead::query()->visibleTo($user)->whereKey($data['ids'])->get();

        DB::transaction(function () use ($leads, $data, $user) {
            foreach ($leads as $lead) {
                match ($data['action']) {
                    'status' => $user->can('update', $lead) && $lead->update(['status_id' => $data['status_id']]),
                    'assign' => $lead->update(['assigned_to' => $data['assigned_to']]),
                    'delete' => $user->can('delete', $lead) && $lead->delete(),
                };
            }
        });

        $verb = ['status' => 'updated', 'assign' => 'reassigned', 'delete' => 'deleted'][$data['action']];

        return back()->with('status', "{$leads->count()} ".str('lead')->plural($leads->count())." {$verb}.");
    }
}
