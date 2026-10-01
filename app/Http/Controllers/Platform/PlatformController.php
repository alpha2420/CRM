<?php

namespace App\Http\Controllers\Platform;

use App\Billing\PlanCatalog;
use App\Billing\SubscriptionManager;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Organization;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The SaaS operator's view across every workspace. This is the only place
 * that deliberately reads across tenants.
 */
class PlatformController extends Controller
{
    public function index(Request $request, PlanCatalog $plans): View
    {
        $organizations = Organization::query()
            ->withCount(['users', 'leads' => fn (Builder $q) => $q->withoutGlobalScope(OrganizationScope::class)])
            ->when($request->query('q'), fn (Builder $q, string $term) => $q->where('name', 'like', "%{$term}%"))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $all = Organization::query()->get();
        $paying = $all->filter->hasPaidAccess();

        return view('platform.index', [
            'organizations' => $organizations,
            'stats' => [
                'workspaces' => $all->count(),
                'trials' => $all->filter->onTrial()->count(),
                'paying' => $paying->count(),
                'suspended' => $all->filter->isSuspended()->count(),
                'mrr' => $paying->sum(fn (Organization $o) => $o->plan()->price),
                'leads' => Lead::withoutGlobalScope(OrganizationScope::class)->count(),
            ],
        ]);
    }

    public function show(Organization $organization, PlanCatalog $plans): View
    {
        return view('platform.show', [
            'organization' => $organization,
            'users' => $organization->users()->orderBy('name')->get(),
            'leadCount' => Lead::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->count(),
            'plans' => $plans->paid(),
        ]);
    }

    public function suspend(Organization $organization): RedirectResponse
    {
        $organization->forceFill(['suspended_at' => $organization->isSuspended() ? null : now()])->save();

        return back()->with('status', $organization->isSuspended() ? 'Workspace suspended.' : 'Workspace reactivated.');
    }

    public function extendTrial(Request $request, Organization $organization, SubscriptionManager $subscriptions): RedirectResponse
    {
        $days = (int) $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']])['days'];
        $subscriptions->extendTrial($organization, $days);

        return back()->with('status', "Trial extended by {$days} days.");
    }

    public function grantPlan(Request $request, Organization $organization, PlanCatalog $plans, SubscriptionManager $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_map(fn ($plan) => $plan->key, $plans->paid()))],
            'paid_until' => ['required', 'date', 'after:today'],
        ]);

        $subscriptions->grantManually($organization, $plans->get($data['plan']), Carbon::parse($data['paid_until']));

        return back()->with('status', 'Plan recorded.');
    }
}
