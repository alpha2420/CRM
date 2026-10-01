<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Integrations\FacebookLeadAds;
use App\Integrations\MetaGraph;
use App\Integrations\WhatsAppService;
use App\Models\Integration;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One callback URL per connected channel: /api/webhooks/meta/{key}. It
 * serves WhatsApp Cloud API events and Facebook/Instagram lead-ad events.
 */
class MetaWebhookController extends Controller
{
    /**
     * Meta's subscription handshake: echo hub.challenge if the token matches.
     */
    public function verify(Request $request, string $key): Response
    {
        $integration = Integration::findByKey($key, IntegrationType::WhatsApp, IntegrationType::Facebook);
        $token = (string) $request->query('hub_verify_token');

        if ($integration === null || $request->query('hub_mode') !== 'subscribe'
            || ! hash_equals((string) $integration->setting('verify_token'), $token)) {
            return response('Forbidden', 403);
        }

        return response((string) $request->query('hub_challenge'));
    }

    public function receive(Request $request, string $key, TenantContext $tenant, WhatsAppService $whatsapp, FacebookLeadAds $facebook): Response
    {
        $integration = Integration::findByKey($key, IntegrationType::WhatsApp, IntegrationType::Facebook);

        if ($integration === null) {
            return response('Not found', 404);
        }

        if (! MetaGraph::hasValidSignature($request->getContent(), (string) $request->header('X-Hub-Signature-256'), $integration->setting('app_secret'))) {
            return response('Invalid signature', 403);
        }

        $tenant->set($integration->organization_id);

        // Acknowledge but ignore traffic for paused or downgraded workspaces.
        if ($integration->acceptsTraffic()) {
            match ($integration->type) {
                IntegrationType::WhatsApp => $whatsapp->handleWebhook($integration, $request->json()->all()),
                IntegrationType::Facebook => $facebook->handleWebhook($integration, $request->json()->all()),
                default => null,
            };
        }

        return response('OK');
    }
}
