<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ApiKeyManager;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.organization', ['organization' => $request->user()->organization]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone:all'],
        ]);
        $data['require_two_factor'] = $request->boolean('require_two_factor');

        $organization = $request->user()->organization;
        $organization->fill(['name' => $data['name']]);
        $organization->forceFill(['timezone' => $data['timezone'], 'require_two_factor' => $data['require_two_factor']]);
        $labels = array_intersect_key(['name' => 'name', 'timezone' => 'time zone', 'require_two_factor' => 'two-factor requirement'], $organization->getDirty());
        $organization->save();

        if ($labels) {
            app(AuditLogger::class)->log('workspace.updated', 'Changed workspace '.implode(', ', $labels), $organization);
        }

        return back()->with('status', 'Workspace saved.');
    }

    public function regenerateApiKey(Request $request, ApiKeyManager $keys): RedirectResponse
    {
        $key = $keys->regenerate($request->user()->organization);
        app(AuditLogger::class)->log('workspace.api_key', 'Created a new API key (the old one stopped working)');

        return back()
            ->with('status', 'New API key created. Copy it now: it will not be shown again.')
            ->with('api_key', $key);
    }
}
