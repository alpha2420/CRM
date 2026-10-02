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

    private function local(?DateTimeInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at ?? now())->setTimezone($this->timezone);
    }
}
