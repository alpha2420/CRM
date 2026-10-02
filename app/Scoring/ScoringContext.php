<?php

namespace App\Scoring;

use App\Enums\StatusType;
use App\Models\Organization;
use Carbon\CarbonImmutable;

/**
 * Facts about the whole workspace that signals compare a lead against,
 * worked out once per scoring run.
 */
final readonly class ScoringContext
{
    /** A source needs this many closed leads before its win rate counts. */
    public const MIN_CLOSED_FOR_SOURCE = 10;

    /**
     * @param  list<int>  $openStages  open status ids, in pipeline order
     * @param  array<int, float>  $sourceWinRates  source id => share of its closed leads that were won
     */
    public function __construct(
        public array $openStages,
        public float $averageValue,
        public array $sourceWinRates,
        public float $overallWinRate,
        public CarbonImmutable $now,
    ) {}

    public static function for(Organization $organization): self
    {
        $statuses = $organization->leadStatuses()->ordered()->get(['id', 'type']);
        $open = $statuses->where('type', StatusType::Open)->pluck('id')->values()->all();
        $won = $statuses->where('type', StatusType::Won)->pluck('id')->all();
        $closed = $statuses->where('type', '!=', StatusType::Open)->pluck('id')->all();

        $closedBySource = $organization->leads()->whereIn('status_id', $closed)
            ->selectRaw('source_id, count(*) as total')->groupBy('source_id')->pluck('total', 'source_id');
        $wonBySource = $organization->leads()->whereIn('status_id', $won)
            ->selectRaw('source_id, count(*) as total')->groupBy('source_id')->pluck('total', 'source_id');

        // Leads without a source are grouped under an empty key: they count
        // towards the overall rate but get no rate of their own.
        $rates = [];
        foreach ($closedBySource as $sourceId => $total) {
            if ((int) $sourceId > 0 && $total >= self::MIN_CLOSED_FOR_SOURCE) {
                $rates[(int) $sourceId] = ($wonBySource[$sourceId] ?? 0) / $total;
            }
        }

        $closedTotal = $closedBySource->sum();

        return new self(
            openStages: array_map('intval', $open),
            averageValue: (float) $organization->leads()->whereIn('status_id', $open)->whereNotNull('value')->avg('value'),
            sourceWinRates: $rates,
            overallWinRate: $closedTotal > 0 ? $wonBySource->sum() / $closedTotal : 0.0,
            now: CarbonImmutable::now(),
        );
    }
}
