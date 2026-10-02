<?php

namespace App\Http\Controllers\Settings;

use App\Enums\IntegrationType;
use App\Enums\StatusType;
use App\Http\Controllers\Controller;
use App\Integrations\IndiaMartLeads;
use App\Integrations\MetaGraph;
use App\Integrations\WhatsAppService;
use App\Models\AdConversion;
use App\Models\Integration;
use App\Models\LeadStatus;
use App\Models\WhatsAppTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    /** Secret settings are never shown again; a blank field keeps the stored value. */
    private const SECRETS = ['access_token', 'app_secret', 'page_access_token', 'crm_key'];

    public function index(Request $request): View
    {
        return view('settings.integrations.index', [
            'organization' => $request->user()->organization,
            'integrations' => Integration::query()->get()->keyBy(fn (Integration $i) => $i->type->value),
        ]);
    }

    public function edit(Request $request, IntegrationType $type): View
    {
        return view('settings.integrations.edit', [
            'type' => $type,
            'integration' => $this->find($type),
            'organization' => $request->user()->organization,
            'templates' => $type === IntegrationType::WhatsApp ? WhatsAppTemplate::query()->orderBy('name')->get() : collect(),
            'stages' => $type === IntegrationType::WhatsApp ? LeadStatus::query()->where('type', StatusType::Open)->ordered()->get() : collect(),
            'conversions' => $type === IntegrationType::WhatsApp
                ? AdConversion::query()->selectRaw('event, status, count(*) as total')->groupBy('event', 'status')->get()
                : collect(),
            'lastConversionError' => $type === IntegrationType::WhatsApp
                ? AdConversion::query()->where('status', AdConversion::FAILED)->latest('updated_at')->value('error')
                : null,
        ]);
    }

    public function update(Request $request, IntegrationType $type): RedirectResponse
    {
        $feature = $type->feature();
        if ($feature !== null && ! $request->user()->organization->canUse($feature)) {
            return redirect()->route('settings.billing')->with('warning', "{$feature->label()} is not included in your plan.");
        }

        $integration = $this->find($type) ?? new Integration(['type' => $type]);
        $input = $request->validate($this->rules($type, $integration->exists));

        if ($type === IntegrationType::WebForm) {
            foreach (['ask_email', 'ask_city', 'ask_message'] as $toggle) {
                $input[$toggle] = $request->boolean($toggle);
            }
        }

        $settings = $integration->settings ?? [];
        foreach ($input as $key => $value) {
            if (in_array($key, self::SECRETS, true) && blank($value)) {
                continue;
            }
            $settings[$key] = is_string($value) ? trim($value) : $value;
        }

        // Values Meta / Google must be told: generated once, then kept.
        match ($type) {
            IntegrationType::WhatsApp, IntegrationType::Facebook => $settings['verify_token'] ??= Str::random(32),
            IntegrationType::Google => $settings['google_key'] ??= Str::random(32),
            IntegrationType::WebForm, IntegrationType::IndiaMart => null,
        };

        $integration->fill(['settings' => $settings, 'is_active' => true])->save();

        return redirect()->route('settings.integrations.edit', $type)->with('status', "{$type->label()} saved.");
    }

    public function destroy(IntegrationType $type): RedirectResponse
    {
        $this->find($type)?->delete();

        return redirect()->route('settings.integrations.index')->with('status', "{$type->label()} disconnected.");
    }

    public function syncTemplates(WhatsAppService $whatsapp): RedirectResponse
    {
        $integration = $this->find(IntegrationType::WhatsApp) ?? abort(404);

        try {
            $count = $whatsapp->syncTemplates($integration);
        } catch (RequestException $e) {
            return back()->withErrors(['templates' => 'WhatsApp said: '.($e->response->json('error.message') ?? 'request failed').' Check the access token and WhatsApp Business Account ID.']);
        }

        app(AuditLogger::class)->log('integration.templates', "Synced {$count} WhatsApp templates");

        return back()->with('status', "Synced {$count} templates from WhatsApp.");
    }

    /**
     * Ask Meta who the saved credentials belong to, and remember the answer.
     */
    public function test(IntegrationType $type, MetaGraph $graph, IndiaMartLeads $indiaMart): RedirectResponse
    {
        $integration = $this->find($type) ?? abort(404);

        if ($type === IntegrationType::IndiaMart) {
            if (! $indiaMart->isDue($integration)) {
                return back()->withErrors(['connection' => 'IndiaMART allows one check every 5 minutes. New enquiries are fetched by themselves; try again in a few minutes.']);
            }
            $added = $indiaMart->pull($integration);
            $error = $integration->fresh()->setting('last_error');

            return $error
                ? back()->withErrors(['connection' => "IndiaMART said: {$error}"])
                : back()->with('status', "Connection works: {$added} new ".Str::plural('enquiry', $added).' added.');
        }

        abort_unless(in_array($type, [IntegrationType::WhatsApp, IntegrationType::Facebook], true), 404);

        try {
            $label = $type === IntegrationType::WhatsApp
                ? (function () use ($graph, $integration) {
                    $number = $graph->whatsappNumber($integration);

                    return trim(($number['display_phone_number'] ?? '').' · '.($number['verified_name'] ?? ''), ' ·')
                        .(isset($number['quality_rating']) ? " (quality: {$number['quality_rating']})" : '');
                })()
                : 'Page “'.($graph->facebookPage($integration)['name'] ?? 'unknown').'”';
        } catch (RequestException $e) {
            return back()->withErrors(['connection' => 'Meta rejected the connection: '.($e->response->json('error.message') ?? 'unknown error').' Check the token and IDs.']);
        } catch (ConnectionException) {
            return back()->withErrors(['connection' => 'Could not reach Meta. Check your internet connection and try again.']);
        }

        $integration->forceFill(['settings' => array_merge($integration->settings ?? [], ['verified_label' => $label, 'verified_at' => now()->toIso8601String()])])->save();

        return back()->with('status', "Connection works: {$label}.");
    }

    private function find(IntegrationType $type): ?Integration
    {
        return Integration::query()->where('type', $type)->first();
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(IntegrationType $type, bool $exists): array
    {
        $secret = $exists ? 'nullable' : 'required';

        return match ($type) {
            IntegrationType::WebForm => [
                'title' => ['required', 'string', 'max:100'],
                'button' => ['required', 'string', 'max:40'],
                'thank_you' => ['required', 'string', 'max:300'],
                'ask_email' => ['boolean'],
                'ask_city' => ['boolean'],
                'ask_message' => ['boolean'],
            ],
            IntegrationType::WhatsApp => [
                'phone_number_id' => ['required', 'string', 'max:40'],
                'waba_id' => ['required', 'string', 'max:40'],
                'access_token' => [$secret, 'string', 'max:1000'],
                'app_secret' => [$secret, 'string', 'max:100'],
                'default_country_code' => ['required', 'regex:/^\+?\d{1,4}$/'],
            ],
            IntegrationType::Facebook => [
                'page_access_token' => [$secret, 'string', 'max:1000'],
                'app_secret' => [$secret, 'string', 'max:100'],
            ],
            IntegrationType::Google => [],
            IntegrationType::IndiaMart => [
                'crm_key' => [$secret, 'string', 'max:200'],
            ],
        };
    }
}
