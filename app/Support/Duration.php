<?php

namespace App\Support;

final class Duration
{
    /**
     * 45 -> "45m", 135 -> "2h 15m", 1590 -> "1d 2h".
     */
    public static function minutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        return match (true) {
            $minutes < 60 => "{$minutes}m",
            $minutes < 1440 => intdiv($minutes, 60).'h '.($minutes % 60).'m',
            default => intdiv($minutes, 1440).'d '.intdiv($minutes % 1440, 60).'h',
        };
    }
}
