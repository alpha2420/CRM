<?php

namespace App\Support;

final class PhoneNumber
{
    /** Validation rule for a normalized number: optional "+", 5-20 digits. */
    public const RULE = 'regex:/^\+?\d{5,20}$/';

    /**
     * Keep digits and a leading "+", so "+91 98765-43210" and
     * "+919876543210" are recognised as the same lead.
     */
    public static function normalize(string $phone): string
    {
        $phone = trim($phone);
        $prefix = str_starts_with($phone, '+') ? '+' : '';

        return $prefix.preg_replace('/\D/', '', $phone);
    }

    /**
     * The one way a lead's number is saved, so the same person is always
     * the same lead: "98765 43210", "098765 43210", "91 98765 43210",
     * "0091 98765 43210" and "+91 98765-43210" are all "+919876543210".
     * A number without a country code gets the workspace's.
     */
    public static function international(string $phone, string $countryCode): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return '';
        }

        $national = ltrim($digits, '0');

        return '+'.match (true) {
            str_starts_with(trim($phone), '+') => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            strlen($national) <= 10 => preg_replace('/\D/', '', $countryCode).$national,
            default => $digits,
        };
    }
}
