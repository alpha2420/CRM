<?php

namespace App\Enums;

enum IntegrationType: string
{
    case WebForm = 'web_form';
    case WhatsApp = 'whatsapp';
    case Facebook = 'facebook';
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::WebForm => 'Website form',
            self::WhatsApp => 'WhatsApp Business',
            self::Facebook => 'Facebook & Instagram lead ads',
            self::Google => 'Google Ads lead forms',
        };
    }

    /** The lead source name given to leads arriving through this channel. */
    public function sourceName(): string
    {
        return match ($this) {
            self::WebForm => 'Website form',
            self::WhatsApp => 'WhatsApp',
            self::Facebook => 'Facebook Ads',
            self::Google => 'Google Ads',
        };
    }

    public function feature(): ?Feature
    {
        return match ($this) {
            self::WebForm => null,
            self::WhatsApp => Feature::WhatsApp,
            self::Facebook, self::Google => Feature::LeadAds,
        };
    }
}
