<?php

namespace App\AdConversions;

/**
 * What Meta is told about a person who came from a Click-to-WhatsApp ad.
 * The names are Meta's own; it refuses others (such as plain "Lead").
 */
enum ConversionEvent: string
{
    case LeadSubmitted = 'LeadSubmitted';
    case QualifiedLead = 'QualifiedLead';
    case Purchase = 'Purchase';

    public function label(): string
    {
        return match ($this) {
            self::LeadSubmitted => 'New lead',
            self::QualifiedLead => 'Qualified lead',
            self::Purchase => 'Customer (won)',
        };
    }
}
