<?php

namespace App\Support;

use App\Models\Organization;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * The workspace's own clock. The database always holds UTC; this converts
 * what people type into UTC and what they see back into their time zone,
 * and gives day/month boundaries ("today", "this month") in local time.
 */
final class LocalTime
{
    public static function zone(): string
    {
        $user = Auth::user();

        if ($user !== null && $user->relationLoaded('organization')) {
            return $user->organization->timezone ?: self::fallback();
        }

        $organizationId = app(TenantContext::class)->id();

        return $organizationId !== null ? self::zoneFor($organizationId) : self::fallback();
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    /**
     * A local date-time typed by a user ("2026-10-05T11:00") as UTC.
     */
    public static function toUtc(?string $local): ?Carbon
    {
        return filled($local) ? Carbon::parse($local, self::zone())->utc() : null;
    }

    /**
     * Shift a UTC instant into a time zone (the workspace's by default).
     */
    public static function of(DateTimeInterface $instant, ?string $zone = null): Carbon
    {
        return Carbon::instance($instant)->setTimezone($zone ?? self::zone());
    }

    public static function startOfToday(): Carbon
    {
        return Carbon::instance(self::now()->startOfDay())->utc();
    }

    public static function endOfToday(): Carbon
    {
        return Carbon::instance(self::now()->endOfDay())->utc();
    }

    public static function startOfMonth(): Carbon
    {
        return Carbon::instance(self::now()->startOfMonth())->utc();
    }

    /**
     * A local calendar date ("2026-10-05") as the UTC start or end of that day.
     */
    public static function dayBoundary(string $date, bool $end = false): Carbon
    {
        $day = CarbonImmutable::parse($date, self::zone());

        return Carbon::instance($end ? $day->endOfDay() : $day->startOfDay())->utc();
    }

    private static function zoneFor(int $organizationId): string
    {
        return once(fn () => Organization::query()->whereKey($organizationId)->value('timezone') ?: self::fallback());
    }

    private static function fallback(): string
    {
        return (string) config('crm.default_timezone');
    }
}
