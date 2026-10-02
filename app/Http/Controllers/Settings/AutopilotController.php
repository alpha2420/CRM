<?php

namespace App\Http\Controllers\Settings;

use App\Ai\LeadAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\AutopilotRequest;
use App\Integrations\WhatsAppService;
use App\Media\ReceivedMedia;
use App\Models\WhatsAppTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutopilotController extends Controller
{
    public function edit(Request $request, WhatsAppService $whatsapp, LeadAssistant $assistant, ReceivedMedia $media): View
    {
        $organization = $request->user()->organization;

        return view('settings.autopilot', [
            'settings' => $organization->autopilot(),
            'whatsapp' => $whatsapp->integrationFor($organization) !== null,
            'templates' => WhatsAppTemplate::query()->orderBy('name')->get()->filter->isApproved(),
            'aiUnavailable' => match (true) {
                ! $assistant->isConfigured() => 'The AI assistant is not set up on this server yet.',
                ! $assistant->availableFor($organization) => 'Needs the AI assistant, which comes with the Pro plan.',
                default => null,
            },
            'voiceUnavailable' => $media->transcriptionUnavailable($organization),
        ]);
    }

    public function update(AutopilotRequest $request): RedirectResponse
    {
        $organization = $request->user()->organization;
        $organization->forceFill(['autopilot' => $request->settings()])->save();

        app(AuditLogger::class)->log('workspace.autopilot', 'Changed Autopilot settings', $organization);

        return back()->with('status', 'Autopilot saved.');
    }
}
