<?php

namespace App\Support;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A workspace's opening hours (Settings → Autopilot), in its own time
 * zone. Anything that contacts leads or moves work on its own (Autopilot,
 * sequences) waits for these hours.
 */
final readonly class WorkingHours
{
    /** Waits longer than this are not worth counting day by day. */
    private const MAX_DAYS = 90;

    public function __construct(
        private int $start,
        private int $end,
        private bool $sundays,
        private string $timezone,
    ) {}

    public static function for(Organization $organization): self
    {
        $settings = $organization->autopilot();

        return new self(
            $settings->number('work_start'),
            $settings->number('work_end'),
            $settings->on('work_sundays'),
            $organization->timezone ?: (string) config('crm.default_timezone'),
        );
    }

    public function isOpen(?DateTimeInterface $at = null): bool
    {
        $local = $this->local($at);

        return ($this->sundays || ! $local->isSunday())
            && $local->hour >= $this->start
            && $local->hour < $this->end;
    }

    /** Today's opening time (as an absolute instant). */
    public function opensToday(): CarbonImmutable
    {
        return $this->local(null)->setTime($this->start, 0);
    }

    /**
     * The seconds between two moments that fall inside working hours: how
     * long a lead really waited, not counting nights and closed days.
     */
    public function secondsBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $start = $this->local($from);
        $end = $this->local($to);
        $total = 0;

        for ($day = $start->startOfDay(), $i = 0; $day->lt($end) && $i < self::MAX_DAYS; $day = $day->addDay(), $i++) {
            if (! $this->sundays && $day->isSunday()) {
                continue;
            }

            $opens = $day->setTime($this->start, 0);
            $closes = $day->setTime($this->end, 0); // 24 = midnight
            $from = $opens->gt($start) ? $opens : $start;
            $until = $closes->lt($end) ? $closes : $end;
            $total += max(0, $until->getTimestamp() - $from->getTimestamp());
        }

        return $total;
    }

    private function local(?DateTimeInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at ?? now())->setTimezone($this->timezone);
    }
}
