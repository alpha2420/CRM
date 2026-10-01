<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ApiKeyManager;
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
        $request->user()->organization->update(
            $request->validate(['name' => ['required', 'string', 'max:100']])
        );

        return back()->with('status', 'Organization updated.');
    }

    public function regenerateApiKey(Request $request, ApiKeyManager $keys): RedirectResponse
    {
        $key = $keys->regenerate($request->user()->organization);

        return back()
            ->with('status', 'New API key created. Copy it now: it will not be shown again.')
            ->with('api_key', $key);
    }
}
