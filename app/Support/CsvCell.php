<?php

namespace App\Support;

final class CsvCell
{
    /**
     * Neutralise spreadsheet formula injection (=, @, +, - prefixes) while
     * leaving phone numbers like "+9198..." and negative numbers intact.
     */
    public static function safe(mixed $value): string
    {
        $value = (string) $value;

        if ($value === '' || is_numeric($value) || preg_match('/^\+\d+$/', $value)) {
            return $value;
        }

        return in_array($value[0], ['=', '@', '+', '-', "\t", "\r"], true) ? "'".$value : $value;
    }
}
