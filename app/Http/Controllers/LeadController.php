<?php

namespace App\Http\Controllers;

use App\Ai\LeadAssistant;
use App\Enums\Feature;
use App\Enums\LeadStage;
use App\Enums\Priority;
use App\Enums\StatusType;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeadController extends Controller
{
    /** Cards shown per board column; the rest are a click away in the list. */
    private const BOARD_CARDS = 40;

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status_id', 'source_id', 'assigned_to', 'priority', 'from', 'to']);

        if ($request->query('view') === 'board') {
            return $this->board($request, $filters);
        }

        $stage = LeadStage::tryFrom((string) $request->query('stage')) ?? LeadStage::All;

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
            'view' => 'list',
            'leads' => $leads,
            'stage' => $stage,
            'filters' => array_filter($filters, fn ($value) => filled($value)),
            'stageCounts' => $stageCounts,
        ] + $this->options($request->user()));
    }

    /**
     * The pipeline as columns, one per status. Each column shows its count,
     * total value and the leads that need attention first.
     *
     * @param  array<string, mixed>  $filters
     */
    private function board(Request $request, array $filters): View
    {
        $filters = array_filter(Arr::except($filters, ['status_id']), fn ($value) => filled($value));
        $leads = fn () => Lead::query()->visibleTo($request->user())->filter($filters);
        $options = $this->options($request->user());

        $totals = $leads()
            ->selectRaw('status_id, count(*) as leads_count, sum(value) as leads_value')
            ->groupBy('status_id')
            ->get()
            ->keyBy('status_id');

        $columns = $options['statuses']->map(fn (LeadStatus $status) => (object) [
            'status' => $status,
            'count' => (int) ($totals[$status->id]->leads_count ?? 0),
            'value' => (float) ($totals[$status->id]->leads_value ?? 0),
            'leads' => $leads()
                ->where('status_id', $status->id)
                ->with(['source', 'assignee'])
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at')
                ->latest('id')
                ->limit(self::BOARD_CARDS)
                ->get(),
        ]);

        return view('leads.index', [
            'view' => 'board',
            'columns' => $columns,
            'filters' => $filters,
            'openValue' => $columns->filter(fn ($c) => $c->status->type === StatusType::Open)->sum('value'),
            'openCount' => $columns->filter(fn ($c) => $c->status->type === StatusType::Open)->sum('count'),
        ] + $options);
    }

    public function create(Request $request): View
    {
        return view('leads.create', [
            'lead' => new Lead([
                'priority' => Priority::Medium,
                'status_id' => LeadStatus::query()->whereKey($request->integer('status_id'))->value('id'),
            ]),
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
        if ($whatsappEnabled) {
            $lead->load('latestWhatsAppMessage');
        }
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
