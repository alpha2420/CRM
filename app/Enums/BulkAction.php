<?php

namespace App\Enums;

/**
 * What the lead list's bulk bar can do to the selected leads.
 */
enum BulkAction: string
{
    case Status = 'status';
    case Assign = 'assign';
    case Delete = 'delete';

    public function pastTense(): string
    {
        return match ($this) {
            self::Status => 'updated',
            self::Assign => 'reassigned',
            self::Delete => 'deleted',
        };
    }
}
