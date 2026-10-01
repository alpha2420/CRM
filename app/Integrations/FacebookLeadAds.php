<?php

namespace App\Integrations;

use App\Enums\IntegrationType;
use App\Jobs\FetchFacebookLead;
use App\Models\Integration;
use App\Models\WebhookEvent;
use App\Services\LeadIntake;
use App\Support\LeadFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Facebook and Instagram lead ads: the webhook only carries a lead id, so
 * the answers are fetched from the Graph API in a queued job.
 */
final class FacebookLeadAds
{
    public function __construct(
        private readonly MetaGraph $graph,
        private readonly LeadIntake $intake,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(Integration $integration, array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) === 'leadgen' && isset($change['value']['leadgen_id'])) {
                    FetchFacebookLead::dispatch($integration->id, (string) $change['value']['leadgen_id']);
                }
            }
        }
    }

    public function import(Integration $integration, string $leadgenId): void
    {
        DB::transaction(function () use ($integration, $leadgenId) {
            if (! WebhookEvent::claim('facebook', $leadgenId)) {
                return;
            }

            $data = LeadFieldMapper::fromFacebook($this->graph->lead($integration, $leadgenId)['field_data'] ?? []);

            if (blank($data['phone'])) {
                Log::warning('Facebook lead skipped: no phone number.', ['leadgen_id' => $leadgenId]);

                return;
            }

            $data['name'] ??= $data['phone'];
            $this->intake->capture($integration->organization, $data, IntegrationType::Facebook->sourceName());
        });
    }
}
