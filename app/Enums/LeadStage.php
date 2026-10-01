<?php

namespace App\Enums;

/**
 * The views of the lead list. Open leads are split into fresh / working /
 * dormant by activity; won and lost come from the status type.
 */
enum LeadStage: string
{
    case All = 'all';
    case Fresh = 'fresh';
    case Working = 'working';
    case Due = 'due';
    case Dormant = 'dormant';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All leads',
            self::Fresh => 'Fresh',
            self::Working => 'In progress',
            self::Due => 'Follow-ups due',
            self::Dormant => 'Dormant',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }
}
