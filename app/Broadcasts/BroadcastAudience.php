<?php

namespace App\Broadcasts;

use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a broadcast goes to. Leads who said STOP or whose data was erased
 * are always left out.
 */
final class BroadcastAudience
{
    /** The largest broadcast, to protect the WhatsApp number's limits. */
    public const MAX = 5000;

    /**
     * @param  array{stage?: string|int|null, source_id?: int|string|null, campaign?: ?string, assigned_to?: int|string|null}  $audience
     * @return Builder<Lead>
     */
    public function query(array $audience): Builder
    {
        $stage = $audience['stage'] ?? 'open';

        return Lead::query()
            ->contactable()
            ->whereNull('erased_at')
            ->when($stage === 'open', fn (Builder $q) => $q->open())
            ->when($stage === 'won', fn (Builder $q) => $q->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Won)->select('id')))
            ->when(is_numeric($stage), fn (Builder $q) => $q->where('status_id', (int) $stage))
            ->filter(array_filter([
                'source_id' => $audience['source_id'] ?? null,
                'campaign' => $audience['campaign'] ?? null,
                'assigned_to' => $audience['assigned_to'] ?? null,
            ], fn ($value) => filled($value)));
    }

    /**
     * In words, e.g. "Open leads · from Facebook Ads · campaign “Diwali offer”".
     *
     * @param  array<string, mixed>  $audience
     */
    public function describe(array $audience): string
    {
        $stage = $audience['stage'] ?? 'open';

        return collect([
            match (true) {
                $stage === 'all' => 'All leads',
                $stage === 'won' => 'Customers (won)',
                is_numeric($stage) => 'Leads in “'.(LeadStatus::query()->whereKey((int) $stage)->value('name') ?? 'a removed stage').'”',
                default => 'Open leads',
            },
            filled($audience['source_id'] ?? null) ? 'from '.(Source::query()->whereKey($audience['source_id'])->value('name') ?? 'a removed source') : null,
            filled($audience['campaign'] ?? null) ? "campaign “{$audience['campaign']}”" : null,
            filled($audience['assigned_to'] ?? null) ? 'owned by '.(User::query()->whereKey($audience['assigned_to'])->value('name') ?? 'a former member') : null,
        ])->filter()->implode(' · ');
    }

    /**
     * @param  array<string, mixed>  $audience
     * @return array{count: int, opted_out: int}
     */
    public function size(array $audience): array
    {
        $everyone = $this->query($audience)->toBase()->getCountForPagination();
        $optedOut = Lead::query()->whereNotNull('opted_out_at')->whereNull('erased_at')->count();

        return ['count' => min($everyone, self::MAX), 'opted_out' => $optedOut];
    }
}
