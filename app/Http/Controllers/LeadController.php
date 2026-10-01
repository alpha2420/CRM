<?php

namespace App\Http\Controllers;

use App\Ai\LeadAssistant;
use App\Enums\Feature;
use App\Enums\LeadStage;
use App\Enums\Priority;
use App\Http\Requests\LeadRequest;
use App\Integrations\WhatsAppService;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $stage = LeadStage::tryFrom((string) $request->query('stage')) ?? LeadStage::All;
        $filters = $request->only(['q', 'status_id', 'source_id', 'assigned_to', 'priority', 'from', 'to']);

        $leads = Lead::query()
            ->visibleTo($request->user())
            ->inStage($stage)
            ->filter($filters)
            ->with(['status', 'source', 'assignee'])
            ->when(
                $stage === LeadStage::Due,
                fn ($q) => $q->orderBy('next_follow_up_at'),
                fn ($q) => $q->latest()->latest('id'),
            )
            ->paginate(25)
            ->withQueryString();

        $stageCounts = collect(LeadStage::cases())->mapWithKeys(fn (LeadStage $s) => [
            $s->value => Lead::query()->visibleTo($request->user())->inStage($s)->count(),
        ]);

        return view('leads.index', [
            'leads' => $leads,
            'stage' => $stage,
            'filters' => array_filter($filters, fn ($value) => filled($value)),
            'stageCounts' => $stageCounts,
        ] + $this->options($request->user()));
    }

    public function create(Request $request): View
    {
        return view('leads.create', [
            'lead' => new Lead(['priority' => Priority::Medium]),
        ] + $this->options($request->user()));
    }

    public function store(LeadRequest $request, LeadService $leads): RedirectResponse
    {
        $user = $request->user();
        $lead = $leads->create($user->organization, $request->leadData(), $user);

        return redirect()->route('leads.show', $lead)->with('status', 'Lead created.');
    }

    public function show(Request $request, Lead $lead, WhatsAppService $whatsapp, LeadAssistant $assistant): View
    {
        Gate::authorize('view', $lead);

        $lead->load(['status', 'source', 'assignee', 'creator', 'activities.user', 'activities.status', 'organization']);
        $whatsappEnabled = $whatsapp->integrationFor($lead->organization) !== null;
        $tab = $whatsappEnabled && $request->query('tab') === 'whatsapp' ? 'whatsapp' : 'activity';

        $data = [
            'lead' => $lead,
            'tab' => $tab,
            'whatsappEnabled' => $whatsappEnabled,
            'aiEnabled' => $lead->organization->canUse(Feature::Ai),
            'aiAvailable' => $assistant->availableFor($lead->organization),
            'aiRemaining' => $assistant->remainingThisMonth($lead->organization),
        ];

        if ($tab === 'whatsapp') {
            $whatsapp->markRead($lead);
            $data += $whatsapp->composerData($lead, (int) $request->query('template') ?: null);
        } elseif ($whatsappEnabled) {
            $data['unreadChats'] = $lead->whatsappMessages()->where('direction', 'in')->whereNull('read_at')->count();
        }

        return view('leads.show', $data + $this->options($request->user()));
    }

    public function edit(Request $request, Lead $lead): View
    {
        Gate::authorize('update', $lead);

        return view('leads.edit', ['lead' => $lead] + $this->options($request->user()));
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->leadData());

        return redirect()->route('leads.show', $lead)->with('status', 'Lead updated.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return redirect()->route('leads.index')->with('status', 'Lead deleted.');
    }

    /**
     * Drop-down options shared by the lead screens.
     *
     * @return array<string, mixed>
     */
    private function options(User $user): array
    {
        return [
            'statuses' => LeadStatus::query()->ordered()->get(),
            'customFields' => CustomField::query()->ordered()->get(),
            'sources' => Source::query()->orderBy('name')->get(),
            'users' => $user->isAdmin()
                ? $user->organization->users()->active()->orderBy('name')->get()
                : collect(),
        ];
    }
}
