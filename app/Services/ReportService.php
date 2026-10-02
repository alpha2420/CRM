<?php

namespace App\Services;

use App\Calls\CallOutcome;
use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\LostReason;
use App\Models\Source;
use App\Models\User;
use App\Speed\ResponseTimes;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Sales reports for a date range. Leads created in the range form the
 * cohort: how many were contacted, how fast, and how many were won.
 */
final class ReportService
{
    public function __construct(private readonly ResponseTimes $responseTimes) {}

    /** Ranges longer than this are charted per week instead of per day. */
    private const MAX_DAILY_COLUMNS = 62;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // $from/$to are local dates; the database holds UTC.
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        [$fromUtc, $toUtc] = [$from->utc(), $to->utc()];
        $wonIds = LeadStatus::query()->where('type', StatusType::Won)->pluck('id');

        $cohort = Lead::query()
            ->visibleTo($user)
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->get(['id', 'source_id', 'campaign', 'assigned_to', 'created_by', 'status_id', 'value', 'created_at', 'first_contacted_at', 'response_seconds', 'closed_at']);

        $previous = $this->previousPeriod($user, $from, $to, $wonIds);
        $wonInPeriod = Lead::query()->visibleTo($user)->whereIn('status_id', $wonIds)->whereBetween('closed_at', [$fromUtc, $toUtc]);

        return [
            'kpis' => [
                'new' => $cohort->count(),
                'new_previous' => $previous['new'],
                'contacted_rate' => $this->rate($cohort->whereNotNull('first_contacted_at')->count(), $cohort->count()),
                'won' => (clone $wonInPeriod)->count(),
                'won_previous' => $previous['won'],
                'won_value' => (float) (clone $wonInPeriod)->sum('value'),
                'win_rate' => $this->rate($cohort->whereIn('status_id', $wonIds)->count(), $cohort->count()),
                'speed' => $this->responseTimes->summary($cohort),
            ],
            'funnel' => [
                'New' => $cohort->count(),
                'Contacted' => $cohort->whereNotNull('first_contacted_at')->count(),
                'Won' => $cohort->whereIn('status_id', $wonIds)->count(),
            ],
            'trend' => $this->trend($cohort, $from, $to),
            'sources' => $this->bySource($cohort, $wonIds),
            'campaigns' => $this->byCampaign($cohort, $wonIds),
            'agents' => $this->byAgent($user, $cohort, $wonIds, $fromUtc, $toUtc),
            'lost_reasons' => $this->lostReasons($user, $fromUtc, $toUtc),
        ];
    }

    /**
     * Why leads lost in the period were lost, most common first.
     *
     * @return list<array{name: string, leads: int}>
     */
    private function lostReasons(User $user, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        $counts = Lead::query()
            ->visibleTo($user)
            ->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Lost)->select('id'))
            ->whereBetween('closed_at', [$fromUtc, $toUtc])
            ->selectRaw('lost_reason_id, count(*) as total')
            ->groupBy('lost_reason_id')
            ->pluck('total', 'lost_reason_id');
        $names = LostReason::query()->pluck('name', 'id');

        return $counts
            ->map(fn (int $total, int|string $reasonId) => ['name' => $names[(int) $reasonId] ?? 'No reason given', 'leads' => $total])
            ->sortByDesc('leads')
            ->values()
            ->all();
    }

    /**
     * @return array{new: int, won: int}
     */
    private function previousPeriod(User $user, CarbonImmutable $from, CarbonImmutable $to, Collection $wonIds): array
    {
        $length = $from->diffInSeconds($to);
        $previousTo = $from->subSecond()->utc();
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
        $counts = $cohort->countBy(fn (Lead $lead) => $key(CarbonImmutable::instance($lead->created_at)->setTimezone($from->timezone)));

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
     * Leads that came from a campaign or ad, best performers first.
     *
     * @return list<array<string, mixed>>
     */
    private function byCampaign(Collection $cohort, Collection $wonIds): array
    {
        return $cohort->whereNotNull('campaign')
            ->groupBy('campaign')
            ->map(fn (Collection $leads, string $campaign) => [
                'name' => $campaign,
                'leads' => $leads->count(),
                'contacted' => $this->rate($leads->whereNotNull('first_contacted_at')->count(), $leads->count()),
                'won' => $won = $leads->whereIn('status_id', $wonIds)->count(),
                'win_rate' => $this->rate($won, $leads->count()),
                'won_value' => (float) $leads->whereIn('status_id', $wonIds)->sum('value'),
            ])
            ->sortByDesc(fn (array $row) => [$row['won_value'], $row['leads']])
            ->values()
            ->take(20)
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
        $calls = LeadActivity::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('call_outcome')
            ->selectRaw('user_id, count(*) as total, sum(case when call_outcome in (?, ?) then 1 else 0 end) as reached', [CallOutcome::Connected->value, CallOutcome::CallBack->value])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $user->organization->users()->orderBy('name')->get()
            ->map(function (User $agent) use ($cohort, $wonIds, $followUps, $calls) {
                $leads = $cohort->where('assigned_to', $agent->id);
                $agentCalls = (int) ($calls[$agent->id]->total ?? 0);

                return [
                    'name' => $agent->name,
                    'active' => $agent->is_active,
                    'leads' => $leads->count(),
                    'follow_ups' => (int) ($followUps[$agent->id] ?? 0),
                    'calls' => $agentCalls,
                    'reached' => $this->rate((int) ($calls[$agent->id]->reached ?? 0), $agentCalls),
                    'won' => $won = $leads->whereIn('status_id', $wonIds)->count(),
                    'win_rate' => $this->rate($won, $leads->count()),
                    'speed' => $this->responseTimes->summary($leads),
                ];
            })
            ->filter(fn (array $row) => $row['leads'] > 0 || $row['follow_ups'] > 0)
            ->values()
            ->all();
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
