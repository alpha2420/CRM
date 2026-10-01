<?php

namespace App\Support;

/**
 * Rupee amounts as people in India read them: "₹2,40,000" in full and
 * "₹12.4L" / "₹1.2Cr" where space is short.
 */
final class Money
{
    public static function full(float|int|string|null $amount): string
    {
        $amount = (int) round((float) $amount);
        $digits = (string) abs($amount);

        // Last three digits, then groups of two (Indian grouping).
        $grouped = strlen($digits) > 3
            ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($digits, 0, -3)).','.substr($digits, -3)
            : $digits;

        return ($amount < 0 ? '-' : '').'₹'.$grouped;
    }

    public static function short(float|int|string|null $amount): string
    {
        $amount = (float) $amount;

        return match (true) {
            $amount >= 1_00_00_000 => '₹'.self::trim($amount / 1_00_00_000).'Cr',
            $amount >= 1_00_000 => '₹'.self::trim($amount / 1_00_000).'L',
            $amount >= 1_000 => '₹'.self::trim($amount / 1_000).'K',
            default => '₹'.number_format($amount),
        };
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}
