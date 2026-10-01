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
}
