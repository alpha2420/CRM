<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Human wording for a follow-up date, with a tone for colouring it:
 * "2d overdue" / "Today 11:00" / "Tomorrow 11:00" / "Fri 14 Oct".
 */
final class FollowUp
{
    /**
     * @return array{text: string, tone: string}
     */
    public static function describe(?CarbonInterface $at): array
    {
        if ($at === null) {
            return ['text' => '—', 'tone' => 'none'];
        }

        $now = now();

        return match (true) {
            $at->isPast() && ! $at->isToday() => ['text' => self::overdue($at, $now), 'tone' => 'overdue'],
            $at->isToday() => ['text' => 'Today '.$at->format('H:i'), 'tone' => $at->isPast() ? 'overdue' : 'today'],
            $at->isTomorrow() => ['text' => 'Tomorrow '.$at->format('H:i'), 'tone' => 'later'],
            $at->diffInDays($now, true) < 7 => ['text' => $at->format('D, H:i'), 'tone' => 'later'],
            default => ['text' => $at->format('d M Y'), 'tone' => 'later'],
        };
    }

    private static function overdue(CarbonInterface $at, CarbonInterface $now): string
    {
        $days = (int) $at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay(), true);

        return $days === 1 ? 'Yesterday' : "{$days}d overdue";
    }
}
