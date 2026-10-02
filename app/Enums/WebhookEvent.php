<?php

namespace App\Enums;

/**
 * What another app can be told about. The value is the "event" field of
 * the JSON we send.
 */
enum WebhookEvent: string
{
    case LeadCreated = 'lead.created';
    case LeadStatusChanged = 'lead.status_changed';
    case LeadWon = 'lead.won';
    case LeadLost = 'lead.lost';
    case LeadAssigned = 'lead.assigned';
    case WhatsAppReceived = 'whatsapp.received';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => 'A new lead arrives',
            self::LeadStatusChanged => 'A lead changes stage',
            self::LeadWon => 'A lead is won',
            self::LeadLost => 'A lead is lost',
            self::LeadAssigned => 'A lead gets a new owner',
            self::WhatsAppReceived => 'A lead sends a WhatsApp message',
        };
    }
}
