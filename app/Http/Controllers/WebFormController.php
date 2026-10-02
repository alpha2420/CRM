<?php

namespace App\Http\Controllers;

use App\Campaigns\Attribution;
use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Services\LeadIntake;
use App\Support\PhoneNumber;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * The hosted lead form (/f/{key}): share the link anywhere or embed it in
 * any website with an iframe. It is stateless (no session or cookies), so
 * it works inside third-party pages.
 */
class WebFormController extends Controller
{
    public function show(string $key): View
    {
        $integration = Integration::findByKey($key, IntegrationType::WebForm);

        return $integration && $integration->acceptsTraffic()
            ? view('forms.show', ['integration' => $integration, 'errors' => null])
            : view('forms.unavailable');
    }

    public function submit(Request $request, string $key, TenantContext $tenant, LeadIntake $intake): View
    {
        $integration = Integration::findByKey($key, IntegrationType::WebForm);

        if ($integration === null || ! $integration->acceptsTraffic()) {
            return view('forms.unavailable');
        }

        // Bots fill every field, including this hidden one.
        if (filled($request->input('website'))) {
            return view('forms.thanks', ['integration' => $integration]);
        }

        $input = $request->only(['name', 'phone', 'email', 'city', 'message']);
        if (filled($input['phone'] ?? null)) {
            $input['phone'] = PhoneNumber::normalize((string) $input['phone']);
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', PhoneNumber::RULE],
            'email' => ['nullable', 'email', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], ['phone.regex' => 'Please enter a valid phone number.']);

        if ($validator->fails()) {
            return view('forms.show', ['integration' => $integration, 'errors' => $validator->errors(), 'old' => $input]);
        }

        $tenant->set($integration->organization_id);
        $data = $validator->validated();
        $data['notes'] = $data['message'] ?? null;
        unset($data['message']);

        $data += Attribution::fromLink($request->only(['utm_campaign', 'gclid', 'fbclid']))->toLead();
        $intake->capture($integration->organization, $data, IntegrationType::WebForm->sourceName());

        return view('forms.thanks', ['integration' => $integration]);
    }
}
