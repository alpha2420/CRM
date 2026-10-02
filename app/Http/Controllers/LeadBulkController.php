<?php

namespace App\Http\Controllers;

use App\Enums\BulkAction;
use App\Models\Lead;
use App\Models\Sequence;
use App\Sequences\SequenceEnroller;
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

    public function __invoke(Request $request, SequenceEnroller $enroller): RedirectResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        // The bar sends one value such as "status:4", "assign:7", "sequence:2" or "delete".
        [$action, $target] = array_pad(explode(':', (string) $request->input('operation'), 2), 2, null);
        $request->merge([
            'action' => $action,
            'status_id' => $action === 'status' ? $target : null,
            'assigned_to' => $action === 'assign' ? $target : null,
            'sequence_id' => $action === 'sequence' ? $target : null,
        ]);

        $data = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::enum(BulkAction::class)->only($user->isAdmin() ? BulkAction::cases() : [BulkAction::Status, BulkAction::Sequence])],
            'status_id' => ['required_if:action,status', 'nullable', Rule::exists('lead_statuses', 'id')->where('organization_id', $organizationId)],
            'assigned_to' => ['required_if:action,assign', 'nullable', Rule::exists('users', 'id')->where('organization_id', $organizationId)->where('is_active', true)],
            'sequence_id' => ['required_if:action,sequence', 'nullable', Rule::exists('sequences', 'id')->where('organization_id', $organizationId)->where('is_active', true)],
        ], ['ids.required' => 'Select at least one lead first.', 'action.required' => 'Choose what to do with the selected leads.']);

        $action = BulkAction::from($data['action']);
        $leads = Lead::query()->visibleTo($user)->whereKey($data['ids'])->get();
        $sequence = $action === BulkAction::Sequence ? Sequence::query()->findOrFail($data['sequence_id']) : null;

        DB::transaction(function () use ($leads, $action, $data, $user, $sequence, $enroller) {
            foreach ($leads as $lead) {
                match ($action) {
                    BulkAction::Status => $user->can('update', $lead) && $lead->update(['status_id' => $data['status_id']]),
                    BulkAction::Assign => $lead->update(['assigned_to' => $data['assigned_to']]),
                    BulkAction::Delete => $user->can('delete', $lead) && $lead->delete(),
                    BulkAction::Sequence => $user->can('update', $lead) && $enroller->enroll($lead, $sequence, $user),
                };
            }
        });

        return back()->with('status', "{$leads->count()} ".str('lead')->plural($leads->count())." {$action->pastTense()}.");
    }
}
