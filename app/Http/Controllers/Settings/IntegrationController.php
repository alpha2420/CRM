<?php

namespace App\Http\Controllers\Settings;

use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Integrations\WhatsAppService;
use App\Models\Integration;
use App\Models\WhatsAppTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    /** Secret settings are never shown again; a blank field keeps the stored value. */
    private const SECRETS = ['access_token', 'app_secret', 'page_access_token'];

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
            IntegrationType::WebForm => null,
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
        };
    }
}
