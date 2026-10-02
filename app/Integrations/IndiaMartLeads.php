<?php

namespace App\Integrations;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\WebhookEvent;
use App\Services\LeadIntake;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brings IndiaMART enquiries into the CRM: every 5 minutes it asks for
 * enquiries since the last check (a little overlap, so none slip through),
 * and adds each one once, as a new lead or as a repeat enquiry.
 */
final class IndiaMartLeads
{
    /** IndiaMART allows one request every 5 minutes. */
    public const INTERVAL_MINUTES = 5;

    /** The first check looks back one day, so old enquiries don't flood in. */
    private const FIRST_LOOK_BACK_HOURS = 24;

    /** What each enquiry type means, for the lead's notes. */
    private const TYPES = ['W' => 'Direct enquiry', 'B' => 'Buy-lead', 'P' => 'Phone call', 'BIZ' => 'Catalogue enquiry', 'WA' => 'WhatsApp enquiry'];

    public function __construct(
        private readonly IndiaMartApi $api,
        private readonly LeadIntake $intake,
        private readonly TenantContext $tenant,
    ) {}

    public function isDue(Integration $integration): bool
    {
        $last = $integration->setting('last_checked_at');

        return $last === null || Carbon::parse($last)->lte(now()->subMinutes(self::INTERVAL_MINUTES));
    }

    /**
     * Fetch and add new enquiries. Returns how many leads were added;
     * the result (or IndiaMART's error) is kept on the integration.
     */
    public function pull(Integration $integration): int
    {
        $this->tenant->set($integration->organization_id);
        $now = now();
        $from = $integration->setting('last_checked_at')
            ? Carbon::parse($integration->setting('last_checked_at'))->subMinutes(self::INTERVAL_MINUTES)
            : $now->copy()->subHours(self::FIRST_LOOK_BACK_HOURS);
        $from = $from->max($now->copy()->subDays(7)->addMinute()); // IndiaMART's 7-day limit

        $reply = $this->api->enquiries((string) $integration->setting('crm_key'), $from, $now);
        $ok = $reply['code'] === 200 || $reply['code'] === IndiaMartApi::NO_LEADS;
        $added = $ok ? $this->import($integration, $reply['leads']) : 0;

        $integration->forceFill(['settings' => array_merge($integration->settings ?? [], [
            'last_checked_at' => $now->toIso8601String(),
            'last_added' => $added,
            'last_error' => $ok ? null : ($reply['message'] ?: "IndiaMART answered with code {$reply['code']}."),
        ])])->save();

        return $added;
    }

    /**
     * @param  list<array<string, mixed>>  $enquiries
     */
    private function import(Integration $integration, array $enquiries): int
    {
        $added = 0;

        foreach ($enquiries as $enquiry) {
            $data = $this->leadData($enquiry);
            $id = (string) ($enquiry['UNIQUE_QUERY_ID'] ?? '');

            if ($data === null || $id === '') {
                continue;
            }

            DB::transaction(function () use ($integration, $id, $data, &$added) {
                // The same enquiry can come back in the overlap: add it once.
                if (WebhookEvent::claim('indiamart', "{$integration->organization_id}:{$id}")) {
                    $added += $this->intake->capture($integration->organization, $data, IntegrationType::IndiaMart->sourceName())->created ? 1 : 0;
                }
            });
        }

        return $added;
    }

    /**
     * @param  array<string, mixed>  $enquiry
     * @return array{name: string, phone: string, email: ?string, company: ?string, city: ?string, notes: string, campaign?: string}|null
     */
    private function leadData(array $enquiry): ?array
    {
        $text = fn (string $key) => trim((string) ($enquiry[$key] ?? '')) ?: null;
        $phone = $text('SENDER_MOBILE') ?? $text('SENDER_MOBILE_ALT') ?? $text('SENDER_PHONE');

        if ($phone === null) {
            return null;
        }

        $product = $text('QUERY_PRODUCT_NAME') ?? $text('QUERY_MCAT_NAME');
        $message = $text('QUERY_MESSAGE');
        $notes = collect([
            $product ? "Product: {$product}" : null,
            $message ? trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $message))) : null,
            'Type: '.(self::TYPES[$enquiry['QUERY_TYPE'] ?? ''] ?? 'Enquiry').($text('UNIQUE_QUERY_ID') ? ' · IndiaMART #'.$text('UNIQUE_QUERY_ID') : ''),
        ])->filter()->implode("\n");

        return array_filter([
            'name' => $text('SENDER_NAME') ?? $phone,
            'phone' => $phone,
            'email' => $text('SENDER_EMAIL'),
            'company' => $text('SENDER_COMPANY') ? Str::limit($text('SENDER_COMPANY'), 150, '') : null,
            'city' => $text('SENDER_CITY') ? Str::limit($text('SENDER_CITY'), 100, '') : null,
            'notes' => Str::limit($notes, 5000, ''),
            'campaign' => $product ? Str::limit("IndiaMART: {$product}", 150, '') : null,
        ], fn ($value) => $value !== null);
    }
}
