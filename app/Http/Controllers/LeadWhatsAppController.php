<?php

namespace App\Http\Controllers;

use App\Integrations\WhatsAppService;
use App\Models\Lead;
use App\Models\WhatsAppTemplate;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadWhatsAppController extends Controller
{
    /**
     * The conversation as an HTML fragment; the lead page polls this.
     */
    public function thread(Lead $lead, WhatsAppService $whatsapp): View
    {
        Gate::authorize('view', $lead);
        $whatsapp->markRead($lead);

        return view('leads._whatsapp_thread', ['messages' => $whatsapp->conversation($lead)]);
    }

    public function send(Request $request, Lead $lead, WhatsAppService $whatsapp): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'body' => ['required_without:template_id', 'nullable', 'string', 'max:4096'],
            'template_id' => ['nullable', Rule::exists('whatsapp_templates', 'id')->where('organization_id', $lead->organization_id)],
            'parameters' => ['array'],
            'parameters.*' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if (isset($data['template_id'])) {
                $template = WhatsAppTemplate::query()->findOrFail($data['template_id']);
                $whatsapp->sendTemplate($lead, $request->user(), $template, array_map('strval', $data['parameters'] ?? []));
            } else {
                $whatsapp->sendText($lead, $request->user(), $data['body']);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return $request->input('from') === 'inbox'
            ? redirect()->route('inbox', ['lead' => $lead->id])
            : redirect()->route('leads.show', ['lead' => $lead, 'tab' => 'whatsapp']);
    }
}
