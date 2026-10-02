<?php

namespace App\Enums;

enum IntegrationType: string
{
    case WebForm = 'web_form';
    case WhatsApp = 'whatsapp';
    case Facebook = 'facebook';
    case Google = 'google';
    case IndiaMart = 'indiamart';

    public function label(): string
    {
        return match ($this) {
            self::WebForm => 'Website form',
            self::WhatsApp => 'WhatsApp Business',
            self::Facebook => 'Facebook & Instagram lead ads',
            self::Google => 'Google Ads lead forms',
            self::IndiaMart => 'IndiaMART',
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
            self::IndiaMart => 'IndiaMART',
        };
    }

    public function feature(): ?Feature
    {
        return match ($this) {
            self::WebForm, self::IndiaMart => null,
            self::WhatsApp => Feature::WhatsApp,
            self::Facebook, self::Google => Feature::LeadAds,
        };
    }
}
