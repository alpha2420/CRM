<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Done = 'done';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Done => 'Done',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
        };
    }
}
