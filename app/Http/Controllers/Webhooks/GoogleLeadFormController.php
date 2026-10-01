<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\WebhookEvent;
use App\Services\LeadIntake;
use App\Support\LeadFieldMapper;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Google Ads lead form extension webhook. Google sends the "key" the
 * customer pasted into Google Ads; it must match ours.
 */
class GoogleLeadFormController extends Controller
{
    public function __invoke(Request $request, string $key, TenantContext $tenant, LeadIntake $intake): JsonResponse
    {
        $integration = Integration::findByKey($key, IntegrationType::Google);

        if ($integration === null || ! hash_equals((string) $integration->setting('google_key'), (string) $request->input('google_key'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tenant->set($integration->organization_id);
        $data = LeadFieldMapper::fromGoogle((array) $request->input('user_column_data', []));

        if (! $integration->acceptsTraffic() || blank($data['phone'])) {
            return response()->json([]);
        }

        if ($request->boolean('is_test')) {
            $data['notes'] = trim('Test lead sent from Google Ads. '.($data['notes'] ?? ''));
        }

        DB::transaction(function () use ($request, $integration, $intake, $data) {
            if (WebhookEvent::claim('google', (string) ($request->input('lead_id') ?: sha1($request->getContent())))) {
                $data['name'] ??= $data['phone'];
                $intake->capture($integration->organization, $data, IntegrationType::Google->sourceName());
            }
        });

        return response()->json([]);
    }
}
