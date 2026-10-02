<?php

namespace App\Integrations;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * IndiaMART's Lead Manager pull API (version 2). IndiaMART asks callers to
 * fetch at most once every 5 minutes and for at most 7 days at a time;
 * times are Indian Standard Time.
 */
final class IndiaMartApi
{
    private const URL = 'https://mapi.indiamart.com/wservce/crm/crmListing/v2/';

    /** IndiaMART's reply when there is simply nothing new. */
    public const NO_LEADS = 204;

    /**
     * @return array{code: int, message: string, leads: list<array<string, mixed>>}
     */
    public function enquiries(string $crmKey, CarbonInterface $from, CarbonInterface $to): array
    {
        $response = Http::acceptJson()->timeout(30)->retry(2, 2000, throw: false)->get(self::URL, [
            'glusr_crm_key' => $crmKey,
            'start_time' => $this->time($from),
            'end_time' => $this->time($to),
        ]);

        return [
            'code' => (int) ($response->json('CODE') ?? $response->status()),
            'message' => (string) ($response->json('MESSAGE') ?? ''),
            'leads' => array_values((array) ($response->json('RESPONSE') ?? [])),
        ];
    }

    /** "01-Jan-202209:30:00": IndiaMART's own format, in IST. */
    private function time(CarbonInterface $at): string
    {
        return $at->copy()->setTimezone('Asia/Kolkata')->format('d-M-YH:i:s');
    }
}
