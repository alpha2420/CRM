<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Role;
use App\Enums\StatusType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoutingRuleRequest;
use App\Models\CustomField;
use App\Models\LeadStatus;
use App\Models\RoutingRule;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Lead routing: who is available, the open-lead limit and the
 * routing rules.
 */
class RoutingController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $request->user()->organization;
        $open = LeadStatus::query()->where('type', StatusType::Open)->select('id');

        return view('settings.routing.index', [
            'organization' => $organization,
            'people' => $organization->users()->active()
                ->withCount(['assignedLeads as open_count' => fn (Builder $q) => $q->whereIn('status_id', $open)])
                ->orderByRaw('role = ? desc', [Role::Agent->value])->orderBy('name')
                ->get(),
            'rules' => RoutingRule::query()->orderBy('position')->orderBy('id')->get(),
        ] + $this->options($request));
    }

    public function updateLimit(Request $request): RedirectResponse
    {
        $data = $request->validate(['max_open_leads' => ['nullable', 'integer', 'min:1', 'max:5000']]);
        $request->user()->organization->forceFill(['max_open_leads' => $data['max_open_leads'] ?? null])->save();

        return back()->with('status', 'Lead limit saved.');
    }

    public function availability(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $user->forceFill(['is_available' => ! $user->is_available])->save();

        return back()->with('status', $user->is_available ? "{$user->name} gets new leads again." : "{$user->name} is away: new leads skip them.");
    }

    public function create(Request $request): View
    {
        return view('settings.routing.form', ['rule' => new RoutingRule(['conditions' => [], 'agent_ids' => []])] + $this->options($request));
    }

    public function store(RoutingRuleRequest $request): RedirectResponse
    {
        RoutingRule::create($request->rule() + ['position' => (int) RoutingRule::query()->max('position') + 1]);

        return redirect()->route('settings.routing.index')->with('status', 'Routing rule is live.');
    }

    public function edit(Request $request, RoutingRule $rule): View
    {
        return view('settings.routing.form', ['rule' => $rule] + $this->options($request));
    }

    public function update(RoutingRuleRequest $request, RoutingRule $rule): RedirectResponse
    {
        $rule->update($request->rule());

        return redirect()->route('settings.routing.index')->with('status', 'Routing rule updated.');
    }

    public function toggle(RoutingRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('status', $rule->is_active ? 'Rule turned on.' : 'Rule paused.');
    }

    /**
     * Rules are checked from the top: move one up or down a place.
     */
    public function move(RoutingRule $rule, string $direction): RedirectResponse
    {
        $rules = RoutingRule::query()->orderBy('position')->orderBy('id')->get()->values();
        $index = $rules->search(fn (RoutingRule $r) => $r->is($rule));
        $swap = $rules[$direction === 'up' ? $index - 1 : $index + 1] ?? null;

        if ($swap !== null) {
            $ordered = $rules->all();
            [$ordered[$index], $ordered[$rules->search($swap)]] = [$swap, $rule];
            foreach (array_values($ordered) as $position => $item) {
                $item->update(['position' => $position + 1]);
            }
        }

        return back();
    }

    public function destroy(RoutingRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('settings.routing.index')->with('status', 'Routing rule deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Request $request): array
    {
        return [
            'sources' => Source::query()->orderBy('name')->get(),
            'fields' => CustomField::query()->ordered()->get(),
            'users' => $request->user()->organization->users()->active()->orderBy('name')->get(),
        ];
    }
}
