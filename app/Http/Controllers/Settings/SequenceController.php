<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SequenceRequest;
use App\Models\Sequence;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SequenceController extends Controller
{
    public function index(): View
    {
        return view('settings.sequences.index', [
            'sequences' => Sequence::query()->with('steps.template')->withCount('activeEnrollments')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Sequence(['is_active' => true, 'stop_on_reply' => true]));
    }

    public function store(SequenceRequest $request): RedirectResponse
    {
        DB::transaction(fn () => Sequence::create($request->sequence())->replaceSteps($request->steps()));

        return redirect()->route('settings.sequences.index')->with('status', 'Sequence saved. Start it from a lead, the lead list or an automation.');
    }

    public function edit(Sequence $sequence): View
    {
        return $this->form($sequence->load('steps'));
    }

    public function update(SequenceRequest $request, Sequence $sequence): RedirectResponse
    {
        DB::transaction(function () use ($request, $sequence) {
            $sequence->update($request->sequence());
            $sequence->replaceSteps($request->steps());
        });

        return redirect()->route('settings.sequences.index')->with('status', 'Sequence updated.');
    }

    public function toggle(Sequence $sequence): RedirectResponse
    {
        $sequence->update(['is_active' => ! $sequence->is_active]);

        return back()->with('status', $sequence->is_active ? 'Sequence turned on.' : 'Sequence paused. Leads in it wait until you turn it back on.');
    }

    public function destroy(Sequence $sequence): RedirectResponse
    {
        $sequence->delete();

        return redirect()->route('settings.sequences.index')->with('status', 'Sequence deleted.');
    }

    private function form(Sequence $sequence): View
    {
        return view('settings.sequences.form', [
            'sequence' => $sequence,
            'templates' => WhatsAppTemplate::query()->orderBy('name')->get()->filter->isApproved(),
        ]);
    }
}
