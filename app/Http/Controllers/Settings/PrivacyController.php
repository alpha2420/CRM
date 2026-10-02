<?php

namespace App\Http\Controllers\Settings;

use App\Consent\OptOut;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Privacy & consent: how the workspace meets India's DPDP Act,
 * opt-out words, and how long closed leads are kept.
 */
class PrivacyController extends Controller
{
    /** Months a workspace can keep closed leads for; null keeps them. */
    public const RETENTION_CHOICES = [6, 12, 24, 36, 60];

    public function edit(Request $request): View
    {
        return view('settings.privacy', [
            'organization' => $request->user()->organization,
            'optedOut' => Lead::query()->whereNotNull('opted_out_at')->whereNull('erased_at')->count(),
            'erased' => Lead::query()->whereNotNull('erased_at')->count(),
            'stopWords' => OptOut::STOP_WORDS,
            'startWords' => OptOut::START_WORDS,
            'choices' => self::RETENTION_CHOICES,
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['retention_months' => ['nullable', 'integer', Rule::in(self::RETENTION_CHOICES)]]);
        $organization = $request->user()->organization;
        $organization->forceFill(['retention_months' => $data['retention_months'] ?? null])->save();

        $audit->log('workspace.retention', $organization->retention_months
            ? "Set closed leads to be erased after {$organization->retention_months} months"
            : 'Set closed leads to be kept', $organization);

        return back()->with('status', 'Privacy settings saved.');
    }
}
