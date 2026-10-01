<?php

namespace App\Enums;

/**
 * Capabilities that depend on the organization's plan. Everything not
 * listed here is part of every plan.
 */
enum Feature: string
{
    case WhatsApp = 'whatsapp';
    case LeadAds = 'lead_ads';
    case Automations = 'automations';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp inbox and messaging',
            self::LeadAds => 'Facebook, Instagram and Google lead ads',
            self::Automations => 'Automations',
            self::Ai => 'AI assistant',
        };
    }
}
