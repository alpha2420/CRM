<?php

namespace App\Campaigns;

/**
 * Which campaign or ad brought a lead in, as far as the channel tells us.
 * It is the lead's first touch: a later enquiry never overwrites it.
 */
final readonly class Attribution
{
    public function __construct(
        public ?string $campaign = null,
        public ?string $adId = null,
        public ?string $clickId = null,
        public ?string $clickType = null,
    ) {}

    /**
     * Click-to-WhatsApp ads: WhatsApp adds a "referral" to the first
     * message someone sends after tapping the ad.
     *
     * @param  array<string, mixed>  $referral
     */
    public static function fromWhatsAppReferral(array $referral): self
    {
        if (! in_array($referral['source_type'] ?? null, ['ad', 'post'], true)) {
            return new self;
        }

        $adId = $referral['source_id'] ?? null;

        return self::clean(($referral['headline'] ?? null) ?: ($adId ? "WhatsApp ad {$adId}" : 'WhatsApp ad'), $adId, $referral['ctwa_clid'] ?? null, 'ctwa');
    }

    /**
     * Facebook and Instagram lead ads, as read from the Graph API.
     *
     * @param  array<string, mixed>  $lead
     */
    public static function fromFacebookLead(array $lead): self
    {
        return self::clean(($lead['campaign_name'] ?? null) ?: ($lead['ad_name'] ?? null), $lead['ad_id'] ?? null, null, null);
    }

    /**
     * Google Ads lead forms send ids only, no names.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromGoogleLead(array $payload): self
    {
        $campaignId = $payload['campaign_id'] ?? null;

        return self::clean($campaignId ? "Google Ads campaign {$campaignId}" : null, $payload['creative_id'] ?? null, $payload['gcl_id'] ?? null, 'gclid');
    }

    /**
     * A shared link such as /f/abc?utm_campaign=diwali, or a website that
     * passes the same fields to the API.
     *
     * @param  array<string, mixed>  $query
     */
    public static function fromLink(array $query): self
    {
        $gclid = $query['gclid'] ?? null;

        return self::clean($query['utm_campaign'] ?? $query['campaign'] ?? null, null, $gclid ?: ($query['fbclid'] ?? null), $gclid ? 'gclid' : 'fbclid');
    }

    public function isEmpty(): bool
    {
        return $this->campaign === null && $this->adId === null && $this->clickId === null;
    }

    /**
     * @return array{campaign?: string, ad_id?: string, click_id?: string, click_type?: string}
     */
    public function toLead(): array
    {
        return array_filter([
            'campaign' => $this->campaign,
            'ad_id' => $this->adId,
            'click_id' => $this->clickId,
            'click_type' => $this->clickId !== null ? $this->clickType : null,
        ], fn (?string $value) => $value !== null);
    }

    private static function clean(mixed $campaign, mixed $adId, mixed $clickId, ?string $clickType): self
    {
        $text = fn (mixed $value, int $max) => is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, $max) : null;

        return new self($text($campaign, 150), $text($adId, 64), $text($clickId, 255), $clickType);
    }
}
