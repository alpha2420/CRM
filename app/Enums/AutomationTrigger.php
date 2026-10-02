<?php

namespace App\Enums;

enum AutomationTrigger: string
{
    case LeadCreated = 'lead_created';
    case StatusChanged = 'status_changed';
    case WhatsAppReceived = 'whatsapp_received';
    case LeadQuiet = 'lead_quiet';
    case FollowUpOverdue = 'follow_up_overdue';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => 'A new lead arrives',
            self::StatusChanged => 'A lead changes status',
            self::WhatsAppReceived => 'A lead sends a WhatsApp message',
            self::LeadQuiet => 'A lead has been quiet for a while',
            self::FollowUpOverdue => 'A follow-up is overdue',
        };
    }

    /** Checked by the scheduler rather than fired by an event. */
    public function isScheduled(): bool
    {
        return $this->afterUnit() !== null;
    }

    /** What "after N" counts for a scheduled trigger. */
    public function afterUnit(): ?string
    {
        return match ($this) {
            self::LeadQuiet => 'days',
            self::FollowUpOverdue => 'hours',
            default => null,
        };
    }
}
