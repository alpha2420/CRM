<?php

namespace App\Speed;

use App\Models\Lead;
use App\Models\User;
use App\Support\WorkingHours;
use Illuminate\Support\Collection;

/**
 * Speed to lead: how quickly leads that arrive on their own (forms, ads,
 * WhatsApp, the API) get their first reply, counted in working hours.
 * Leads answered within five minutes are far more likely to buy. Leads a
 * team member adds by hand are left out: they have already talked.
 */
final class ResponseTimes
{
    public const TARGET_SECONDS = 300;

    /**
     * @param  Collection<int, Lead>  $leads  leads that arrived in a period
     * @return array{arrived: int, answered: int, on_time: int, rate: int, median: ?int}
     */
    public function summary(Collection $leads): array
    {
        $arrived = $leads->whereNull('created_by');
        $times = $arrived->whereNotNull('response_seconds')->pluck('response_seconds')->map(fn ($s) => (int) $s)->sort()->values();
        $onTime = $times->filter(fn (int $seconds) => $seconds <= self::TARGET_SECONDS)->count();

        return [
            'arrived' => $arrived->count(),
            'answered' => $times->count(),
            'on_time' => $onTime,
            'rate' => $arrived->isEmpty() ? 0 : (int) round($onTime / $arrived->count() * 100),
            'median' => $times->isEmpty() ? null : (int) $times->median(),
        ];
    }

    /**
     * Open leads from the last week still waiting for a first reply, the
     * longest wait first, with how long each has waited in working hours.
     * Admins see everyone's unless $onlyTheirs.
     *
     * @return Collection<int, array{lead: Lead, seconds: int}>
     */
    public function waiting(User $user, int $limit = 5, bool $onlyTheirs = false): Collection
    {
        $hours = WorkingHours::for($user->organization);

        return Lead::query()->visibleTo($user)->open()
            ->when($onlyTheirs, fn ($query) => $query->where('assigned_to', $user->id))
            ->whereNull('created_by')
            ->whereNull('first_contacted_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->with('source')
            ->oldest()
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead) => ['lead' => $lead, 'seconds' => $hours->secondsBetween($lead->created_at, now())]);
    }
}
