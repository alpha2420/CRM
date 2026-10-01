<?php

namespace App\Support;

/**
 * WhatsApp addresses people by full international number, digits only
 * ("919876543210"). Leads are often saved without the country code.
 */
final class WhatsAppNumber
{
    public static function fromPhone(string $phone, string $defaultCountryCode): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with(trim($phone), '+') || strlen($digits) > 10) {
            return $digits;
        }

        return preg_replace('/\D/', '', $defaultCountryCode).ltrim($digits, '0');
    }

    /**
     * The ways an incoming WhatsApp number may have been saved on a lead.
     *
     * @return list<string>
     */
    public static function storedVariants(string $waId, string $defaultCountryCode): array
    {
        $variants = ['+'.$waId, $waId];
        $countryCode = preg_replace('/\D/', '', $defaultCountryCode);

        if ($countryCode !== '' && str_starts_with($waId, $countryCode)) {
            $local = substr($waId, strlen($countryCode));
            array_push($variants, $local, '0'.$local);
        }

        return $variants;
    }
}
