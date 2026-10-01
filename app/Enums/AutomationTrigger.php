<?php

namespace App\Enums;

enum AutomationTrigger: string
{
    case LeadCreated = 'lead_created';
    case StatusChanged = 'status_changed';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => 'A new lead arrives',
            self::StatusChanged => 'A lead changes status',
        };
    }
}
