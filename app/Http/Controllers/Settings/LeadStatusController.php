<?php

namespace App\Http\Controllers\Settings;

use App\Enums\StatusType;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadStatusRequest;
use App\Models\LeadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeadStatusController extends Controller
{
    public function index(): View
    {
        return view('settings.statuses', [
            'statuses' => LeadStatus::query()->ordered()->withCount('leads')->get(),
        ]);
    }

    public function store(LeadStatusRequest $request): RedirectResponse
    {
        LeadStatus::create($request->validated());

        return back()->with('status', 'Status added.');
    }

    public function update(LeadStatusRequest $request, LeadStatus $status): RedirectResponse
    {
        if ($status->type === StatusType::Open && $request->enum('type', StatusType::class) !== StatusType::Open && $this->isLastOpenStatus($status)) {
            return back()->withErrors(['type' => 'Keep at least one open status: new leads start in it.']);
        }

        $status->update($request->validated());

        return back()->with('status', 'Status updated.');
    }

    public function destroy(LeadStatus $status): RedirectResponse
    {
        if ($status->leads()->exists()) {
            return back()->withErrors(['status' => "\"{$status->name}\" is used by leads. Move them to another status first."]);
        }

        if ($status->type === StatusType::Open && $this->isLastOpenStatus($status)) {
            return back()->withErrors(['status' => 'Keep at least one open status: new leads start in it.']);
        }

        $status->delete();

        return back()->with('status', 'Status deleted.');
    }

    private function isLastOpenStatus(LeadStatus $status): bool
    {
        return ! LeadStatus::query()
            ->where('type', StatusType::Open)
            ->whereKeyNot($status->id)
            ->exists();
    }
}
