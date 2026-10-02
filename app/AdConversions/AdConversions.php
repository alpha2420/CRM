<?php

namespace App\AdConversions;

use App\Enums\IntegrationType;
use App\Enums\StatusType;
use App\Integrations\MetaGraph;
use App\Jobs\SendAdConversion;
use App\Models\AdConversion;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Tenancy\OrganizationScope;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Tells Meta which people from Click-to-WhatsApp ads became qualified
 * leads and customers (the Conversions API for business messaging), so
 * Meta shows the ads to more people like them. Each result is reported
 * once per lead, and only while the workspace has it switched on.
 */
final class AdConversions
{
    /** Click ids from Click-to-WhatsApp ads. */
    public const CLICK_TYPE = 'ctwa';

    public function __construct(private readonly MetaGraph $graph) {}

    /** The WhatsApp connection, when ad conversions are set up and on. */
    public function connectionFor(int $organizationId): ?Integration
    {
        $whatsapp = Integration::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('type', IntegrationType::WhatsApp)
            ->where('is_active', true)
            ->first();

        return $whatsapp?->setting('conversions_on') && $whatsapp->setting('dataset_id') ? $whatsapp : null;
    }

    /**
     * Find (or let Meta create) the conversions dataset of the WhatsApp
     * Business Account, and switch reporting on.
     *
     * @throws RequestException when Meta refuses, e.g. a missing permission
     * @throws ConnectionException when Meta cannot be reached
     */
    public function setUp(Integration $whatsapp): string
    {
        $datasetId = $this->graph->conversionDataset($whatsapp);
        $qualified = LeadStatus::query()->where('name', 'Interested')->value('id');

        $whatsapp->forceFill(['settings' => array_merge($whatsapp->settings ?? [], [
            'dataset_id' => $datasetId,
            'conversions_on' => true,
            'qualified_status_id' => $whatsapp->setting('qualified_status_id') ?? $qualified,
        ])])->save();

        return $datasetId;
    }

    /**
     * Report this result for the lead, unless it did not come from a
     * Click-to-WhatsApp ad or was reported already.
     */
    public function report(Lead $lead, ConversionEvent $event): void
    {
        if ($lead->click_type !== self::CLICK_TYPE || blank($lead->click_id) || $this->connectionFor($lead->organization_id) === null) {
            return;
        }

        if (AdConversion::withoutGlobalScope(OrganizationScope::class)->where('lead_id', $lead->id)->where('event', $event)->exists()) {
            return;
        }

        $conversion = new AdConversion(['event' => $event]);
        $conversion->organization_id = $lead->organization_id;
        $conversion->lead()->associate($lead);
        $conversion->save();
        SendAdConversion::dispatch($conversion->id)->afterCommit();
    }

    /**
     * The lead reached a stage: qualified once it is at or past the stage
     * chosen in settings, a customer when won.
     */
    public function stageReached(Lead $lead): void
    {
        $whatsapp = $lead->click_type === self::CLICK_TYPE ? $this->connectionFor($lead->organization_id) : null;
        $status = $lead->status()->withoutGlobalScope(OrganizationScope::class)->first();

        if ($whatsapp === null || $status === null) {
            return;
        }

        $qualified = LeadStatus::withoutGlobalScope(OrganizationScope::class)->find($whatsapp->setting('qualified_status_id'));
        $won = $status->type === StatusType::Won;

        if ($won || ($qualified !== null && $status->type === StatusType::Open && $status->sort_order >= $qualified->sort_order)) {
            $this->report($lead, ConversionEvent::QualifiedLead);
        }

        if ($won) {
            $this->report($lead, ConversionEvent::Purchase);
        }
    }

    /** Send one queued result to Meta and remember how it went. */
    public function send(AdConversion $conversion): void
    {
        $lead = $conversion->lead()->withoutGlobalScope(OrganizationScope::class)->first();
        $whatsapp = $this->connectionFor($conversion->organization_id);

        if ($lead === null || $whatsapp === null) {
            $conversion->update(['status' => AdConversion::FAILED, 'error' => 'Ad conversions are switched off.']);

            return;
        }

        $event = [
            'event_name' => $conversion->event->value,
            'event_time' => now()->timestamp,
            'event_id' => "lead-{$lead->id}-{$conversion->event->value}",
            'action_source' => 'business_messaging',
            'messaging_channel' => 'whatsapp',
            'user_data' => [
                'whatsapp_business_account_id' => (string) $whatsapp->setting('waba_id'),
                'ctwa_clid' => $lead->click_id,
            ],
        ];

        if ($conversion->event === ConversionEvent::Purchase) {
            $event['custom_data'] = ['currency' => 'INR', 'value' => (float) ($lead->value ?? 0)];
        }

        try {
            $this->graph->sendConversions($whatsapp, (string) $whatsapp->setting('dataset_id'), [$event]);
            $conversion->update(['status' => AdConversion::SENT, 'error' => null, 'sent_at' => now()]);
        } catch (RequestException $e) {
            if ($e->response->serverError()) {
                throw $e; // temporary: the job tries again
            }
            $conversion->update(['status' => AdConversion::FAILED, 'error' => mb_substr((string) ($e->response->json('error.error_user_msg') ?? $e->response->json('error.message') ?? 'Meta refused the event.'), 0, 500)]);
        }
    }
}
