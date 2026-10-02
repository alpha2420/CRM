<?php

namespace App\Enums;

enum AppointmentType: string
{
    case Meeting = 'meeting';
    case SiteVisit = 'site_visit';
    case Demo = 'demo';
    case Call = 'call';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Meeting',
            self::SiteVisit => 'Site visit',
            self::Demo => 'Demo',
            self::Call => 'Call',
        };
    }
}
