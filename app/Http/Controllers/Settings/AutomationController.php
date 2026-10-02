<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutomationRequest;
use App\Models\Automation;
use App\Models\CustomField;
use App\Models\LeadStatus;
use App\Models\Sequence;
use App\Models\Source;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(Request $request): View
    {
        return view('settings.automations.index', [
            'automations' => Automation::query()->orderBy('name')->get(),
        ] + $this->options($request));
    }

    public function create(Request $request): View
    {
        return view('settings.automations.form', ['automation' => new Automation(['is_active' => true])] + $this->options($request));
    }

    public function store(AutomationRequest $request): RedirectResponse
    {
        Automation::create($request->automation());

        return redirect()->route('settings.automations.index')->with('status', 'Automation is live.');
    }

    public function edit(Request $request, Automation $automation): View
    {
        return view('settings.automations.form', ['automation' => $automation] + $this->options($request));
    }

    public function update(AutomationRequest $request, Automation $automation): RedirectResponse
    {
        $automation->update($request->automation());

        return redirect()->route('settings.automations.index')->with('status', 'Automation updated.');
    }

    public function toggle(Automation $automation): RedirectResponse
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        return back()->with('status', $automation->is_active ? 'Automation turned on.' : 'Automation paused.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $automation->delete();

        return redirect()->route('settings.automations.index')->with('status', 'Automation deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Request $request): array
    {
        return [
            'sources' => Source::query()->orderBy('name')->get(),
            'statuses' => LeadStatus::query()->ordered()->get(),
            'users' => $request->user()->organization->users()->active()->orderBy('name')->get(),
            'templates' => WhatsAppTemplate::query()->where('status', 'APPROVED')->orderBy('name')->get(),
            'sequences' => Sequence::query()->orderBy('name')->get(),
            'fields' => CustomField::query()->ordered()->get(),
        ];
    }
}
