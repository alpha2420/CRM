<?php

namespace App\Support;

/**
 * Initials and a stable colour for a person or company, so the same name
 * always gets the same avatar.
 */
final class Avatar
{
    private const COLORS = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#4f46e5', '#ca8a04'];

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn (string $word) => ! preg_match('/^(mr|mrs|ms|dr|prof)\.?$/i', $word)));

        if ($words === []) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public static function color(?string $name): string
    {
        return self::COLORS[crc32((string) $name) % count(self::COLORS)];
    }
}
