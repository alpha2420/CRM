<?php

namespace App\Services;

use App\Enums\LeadStage;
use App\Enums\Role;
use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class DashboardStats
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $leads = fn (): Builder => Lead::query()->visibleTo($user);
        $wonStatusIds = LeadStatus::query()->where('type', StatusType::Won)->select('id');

        $total = $leads()->count();
        $won = $leads()->whereIn('status_id', $wonStatusIds)->count();

        $openStatusIds = LeadStatus::query()->where('type', StatusType::Open)->select('id');

        return [
            'total' => $total,
            'open' => $leads()->whereIn('status_id', $openStatusIds)->count(),
            'won_this_month' => $leads()->whereIn('status_id', $wonStatusIds)->where('closed_at', '>=', now()->startOfMonth())->count(),
            'new_today' => $leads()->where('created_at', '>=', today())->count(),
            'due' => $leads()->inStage(LeadStage::Due)->count(),
            'dormant' => $leads()->inStage(LeadStage::Dormant)->count(),
            'won' => $won,
            'conversion' => $total > 0 ? round($won / $total * 100, 1) : 0.0,

            'by_status' => LeadStatus::query()->ordered()
                ->withCount(['leads' => fn (Builder $q) => $q->visibleTo($user)])
                ->get(),

            'by_source' => Source::query()
                ->withCount(['leads' => fn (Builder $q) => $q->visibleTo($user)])
                ->orderByDesc('leads_count')
                ->get(),

            'by_agent' => $user->isAdmin()
                ? $user->organization->users()
                    ->where('role', Role::Agent)
                    ->withCount([
                        'assignedLeads',
                        'assignedLeads as won_count' => fn (Builder $q) => $q->whereIn('status_id', $wonStatusIds),
                    ])
                    ->orderBy('name')
                    ->get()
                : collect(),

            'monthly' => $this->monthlyNewLeads($leads),

            'upcoming' => $leads()->inStage(LeadStage::Due)
                ->with(['status', 'assignee'])
                ->orderBy('next_follow_up_at')
                ->limit(8)
                ->get(),
        ];
    }

    /**
     * New leads per month for the last six months (one indexed count each,
     * which keeps this database-agnostic).
     *
     * @param  callable(): Builder  $leads
     * @return array<string, int>
     */
    private function monthlyNewLeads(callable $leads): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $months[$start->format('M Y')] = $leads()
                ->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])
                ->count();
        }

        return $months;
    }
}
