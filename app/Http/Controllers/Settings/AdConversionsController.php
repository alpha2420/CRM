<?php

namespace App\Http\Controllers\Settings;

use App\AdConversions\AdConversions;
use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\AuditLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings → Integrations → WhatsApp → Ad results: report Click-to-WhatsApp
 * results to Meta.
 */
class AdConversionsController extends Controller
{
    public function setUp(AdConversions $conversions, AuditLogger $audit): RedirectResponse
    {
        $whatsapp = $this->whatsapp();

        try {
            $datasetId = $conversions->setUp($whatsapp);
        } catch (RequestException $e) {
            return back()->withErrors(['conversions' => 'Meta said: '.($e->response->json('error.message') ?? 'request failed').' The access token needs the whatsapp_business_manage_events permission.']);
        } catch (ConnectionException) {
            return back()->withErrors(['conversions' => 'Could not reach Meta. Please try again.']);
        }

        $audit->log('integration.conversions', "Turned on ad results for Meta (dataset {$datasetId})", $whatsapp);

        return back()->with('status', 'Ad results are on: Meta will hear which ad leads qualify and buy.');
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $whatsapp = $this->whatsapp();
        $data = $request->validate([
            'qualified_status_id' => ['nullable', Rule::exists('lead_statuses', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);

        $whatsapp->forceFill(['settings' => array_merge($whatsapp->settings ?? [], [
            'conversions_on' => $request->boolean('conversions_on'),
            'qualified_status_id' => isset($data['qualified_status_id']) ? (int) $data['qualified_status_id'] : null,
        ])])->save();
        $audit->log('integration.conversions', $request->boolean('conversions_on') ? 'Changed ad result settings' : 'Turned off ad results for Meta', $whatsapp);

        return back()->with('status', 'Ad results saved.');
    }

    private function whatsapp(): Integration
    {
        return Integration::query()->where('type', IntegrationType::WhatsApp)->first() ?? abort(404);
    }
}
