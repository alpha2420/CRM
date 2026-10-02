<?php

namespace App\Support;

use DateTimeZone;

/**
 * Time zone names as browsers report them. Chrome still uses some old
 * names (India is "Asia/Calcutta", not "Asia/Kolkata") that PHP keeps only
 * as legacy aliases, so they fail a normal time zone check. This turns
 * them into today's names.
 */
final class TimeZoneName
{
    /** Old names Chrome reports => today's name for the same zone. */
    private const RENAMED = [
        'Africa/Asmera' => 'Africa/Asmara',
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
        'America/Catamarca' => 'America/Argentina/Catamarca',
        'America/Coral_Harbour' => 'America/Atikokan',
        'America/Cordoba' => 'America/Argentina/Cordoba',
        'America/Godthab' => 'America/Nuuk',
        'America/Indianapolis' => 'America/Indiana/Indianapolis',
        'America/Jujuy' => 'America/Argentina/Jujuy',
        'America/Louisville' => 'America/Kentucky/Louisville',
        'America/Mendoza' => 'America/Argentina/Mendoza',
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Rangoon' => 'Asia/Yangon',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Atlantic/Faeroe' => 'Atlantic/Faroe',
        'Europe/Kiev' => 'Europe/Kyiv',
        'Pacific/Enderbury' => 'Pacific/Kanton',
        'Pacific/Ponape' => 'Pacific/Pohnpei',
        'Pacific/Truk' => 'Pacific/Chuuk',
    ];

    /**
     * Today's name for a zone, or null when the name is not a time zone.
     */
    public static function current(string $name): ?string
    {
        $name = self::RENAMED[$name] ?? $name;

        return in_array($name, DateTimeZone::listIdentifiers(), true) ? $name : null;
    }
}
