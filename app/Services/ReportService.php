<?php

namespace App\Services;

use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Sales reports for a date range. Leads created in the range form the
 * cohort: how many were contacted, how fast, and how many were won.
 */
final class ReportService
{
    /** Ranges longer than this are charted per week instead of per day. */
    private const MAX_DAILY_COLUMNS = 62;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $wonIds = LeadStatus::query()->where('type', StatusType::Won)->pluck('id');

        $cohort = Lead::query()
            ->visibleTo($user)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'source_id', 'assigned_to', 'status_id', 'value', 'created_at', 'first_contacted_at', 'closed_at']);

        $previous = $this->previousPeriod($user, $from, $to, $wonIds);
        $wonInPeriod = Lead::query()->visibleTo($user)->whereIn('status_id', $wonIds)->whereBetween('closed_at', [$from, $to]);

        return [
            'kpis' => [
                'new' => $cohort->count(),
                'new_previous' => $previous['new'],
                'contacted_rate' => $this->rate($cohort->whereNotNull('first_contacted_at')->count(), $cohort->count()),
                'won' => (clone $wonInPeriod)->count(),
                'won_previous' => $previous['won'],
                'won_value' => (float) (clone $wonInPeriod)->sum('value'),
                'win_rate' => $this->rate($cohort->whereIn('status_id', $wonIds)->count(), $cohort->count()),
                'first_contact_minutes' => $this->averageFirstContact($cohort),
            ],
            'funnel' => [
                'New' => $cohort->count(),
                'Contacted' => $cohort->whereNotNull('first_contacted_at')->count(),
                'Won' => $cohort->whereIn('status_id', $wonIds)->count(),
            ],
            'trend' => $this->trend($cohort, $from, $to),
            'sources' => $this->bySource($cohort, $wonIds),
            'agents' => $this->byAgent($user, $cohort, $wonIds, $from, $to),
        ];
    }

    /**
     * @return array{new: int, won: int}
     */
    private function previousPeriod(User $user, CarbonImmutable $from, CarbonImmutable $to, Collection $wonIds): array
    {
        $length = $from->diffInSeconds($to);
        $previousTo = $from->subSecond();
        $previousFrom = $previousTo->subSeconds((int) $length);

        return [
            'new' => Lead::query()->visibleTo($user)->whereBetween('created_at', [$previousFrom, $previousTo])->count(),
            'won' => Lead::query()->visibleTo($user)->whereIn('status_id', $wonIds)->whereBetween('closed_at', [$previousFrom, $previousTo])->count(),
        ];
    }

    /**
     * New leads per day (or per week for long ranges).
     *
     * @return array{unit: string, points: list<array{label: string, date: string, count: int}>}
     */
    private function trend(Collection $cohort, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $weekly = $from->diffInDays($to) > self::MAX_DAILY_COLUMNS;
        $key = fn ($date) => $weekly ? $date->startOfWeek()->toDateString() : $date->toDateString();
        $counts = $cohort->countBy(fn (Lead $lead) => $key(CarbonImmutable::parse($lead->created_at)));

        $points = [];
        foreach (CarbonPeriod::create($from, $weekly ? '1 week' : '1 day', $to) as $day) {
            $day = CarbonImmutable::parse($weekly ? $day->startOfWeek() : $day);
            $points[$day->toDateString()] = [
                'label' => $weekly ? 'Week of '.$day->format('d M') : $day->format('D d M'),
                'date' => $day->format('d M'),
                'count' => $counts[$day->toDateString()] ?? 0,
            ];
        }

        return ['unit' => $weekly ? 'week' : 'day', 'points' => array_values($points)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bySource(Collection $cohort, Collection $wonIds): array
    {
        $names = Source::query()->pluck('name', 'id');

        return $cohort->groupBy(fn (Lead $lead) => $lead->source_id ?? 0)
            ->map(fn (Collection $leads, int $sourceId) => [
                'name' => $names[$sourceId] ?? 'No source',
                'leads' => $leads->count(),
                'contacted' => $this->rate($leads->whereNotNull('first_contacted_at')->count(), $leads->count()),
                'won' => $won = $leads->whereIn('status_id', $wonIds)->count(),
                'win_rate' => $this->rate($won, $leads->count()),
                'won_value' => (float) $leads->whereIn('status_id', $wonIds)->sum('value'),
            ])
            ->sortByDesc('leads')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byAgent(User $user, Collection $cohort, Collection $wonIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $followUps = LeadActivity::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return $user->organization->users()->orderBy('name')->get()
            ->map(function (User $agent) use ($cohort, $wonIds, $followUps) {
                $leads = $cohort->where('assigned_to', $agent->id);

                return [
                    'name' => $agent->name,
                    'active' => $agent->is_active,
                    'leads' => $leads->count(),
                    'follow_ups' => (int) ($followUps[$agent->id] ?? 0),
                    'won' => $won = $leads->whereIn('status_id', $wonIds)->count(),
                    'win_rate' => $this->rate($won, $leads->count()),
                    'first_contact_minutes' => $this->averageFirstContact($leads),
                ];
            })
            ->filter(fn (array $row) => $row['leads'] > 0 || $row['follow_ups'] > 0)
            ->values()
            ->all();
    }

    private function averageFirstContact(Collection $leads): ?int
    {
        $minutes = $leads->whereNotNull('first_contacted_at')
            ->map(fn (Lead $lead) => max(0, (int) $lead->created_at->diffInMinutes($lead->first_contacted_at)));

        return $minutes->isEmpty() ? null : (int) round($minutes->avg());
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
