<?php

namespace App\Support;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;

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
     * The moment some working minutes after another, in UTC: a lead that
     * arrives at night is due soon after opening, not at 1 am.
     */
    public function after(DateTimeInterface $from, int $minutes): Carbon
    {
        $at = $this->local($from);
        $left = $minutes * 60;

        for ($i = 0; $i < self::MAX_DAYS; $i++, $at = $at->addDay()->startOfDay()) {
            if (! $this->sundays && $at->isSunday()) {
                continue;
            }

            $opens = $at->setTime($this->start, 0);
            $start = $at->gt($opens) ? $at : $opens;
            $open = $at->setTime($this->end, 0)->getTimestamp() - $start->getTimestamp();

            if ($left <= $open) {
                return Carbon::instance($start->addSeconds($left))->utc();
            }

            $left -= max(0, $open);
        }

        return Carbon::instance($at)->utc();
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
