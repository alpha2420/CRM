<?php

namespace App\Http\Controllers;

use App\Broadcasts\BroadcastAudience;
use App\Broadcasts\BroadcastSender;
use App\Http\Requests\BroadcastRequest;
use App\Jobs\SendBroadcast;
use App\Models\Broadcast;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\WhatsAppTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Broadcasts: one approved WhatsApp template to a chosen group of leads.
 */
class BroadcastController extends Controller
{
    public function index(): View
    {
        return view('broadcasts.index', [
            'broadcasts' => Broadcast::query()->with('user')->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request, BroadcastAudience $audience): View
    {
        $chosen = (array) $request->old('audience', ['stage' => 'open']);
        $templates = WhatsAppTemplate::query()->orderBy('name')->get()->filter->isApproved()->values();
        $template = $templates->firstWhere('id', (int) $request->old('whatsapp_template_id'));
        $size = $audience->size($chosen);

        return view('broadcasts.create', [
            'templates' => $templates,
            'template' => $template,
            'chosen' => $chosen,
            'size' => $size,
            'cost' => BroadcastSender::estimatedCost($template?->category, $size['count']),
            'stages' => LeadStatus::query()->ordered()->get(),
            'sources' => Source::query()->orderBy('name')->get(),
            'campaigns' => Lead::query()->whereNotNull('campaign')->distinct()->orderBy('campaign')->limit(200)->pluck('campaign'),
            'people' => $request->user()->organization->users()->active()->orderBy('name')->get(),
            'max' => BroadcastAudience::MAX,
        ]);
    }

    public function store(BroadcastRequest $request, AuditLogger $audit): RedirectResponse
    {
        // "Count leads" only updates the summary.
        if ($request->has('preview')) {
            return redirect()->route('broadcasts.create')->withInput();
        }

        $template = WhatsAppTemplate::query()->findOrFail($request->validated('whatsapp_template_id'));
        $broadcast = new Broadcast([
            'name' => $request->validated('name'),
            'audience' => $request->audience(),
            'values' => array_filter((array) $request->validated('values', []), fn ($value) => filled($value)),
        ]);
        $broadcast->organization_id = $request->user()->organization_id;
        $broadcast->user()->associate($request->user());
        $broadcast->template()->associate($template);
        $broadcast->template_name = $template->name;
        $broadcast->status = Broadcast::SENDING;
        $broadcast->save();

        SendBroadcast::dispatch($broadcast->id);
        $audit->log('broadcast.sent', "Sent the broadcast “{$broadcast->name}” ({$template->name})", $broadcast);

        return redirect()->route('broadcasts.show', $broadcast)->with('status', 'Broadcast is on its way. Results update here as WhatsApp delivers it.');
    }

    public function show(Broadcast $broadcast): View
    {
        return view('broadcasts.show', [
            'broadcast' => $broadcast->load(['template', 'user']),
            'results' => $broadcast->results(),
            'recipients' => $broadcast->messages()->with('lead')->latest('id')->paginate(50),
        ]);
    }
}
