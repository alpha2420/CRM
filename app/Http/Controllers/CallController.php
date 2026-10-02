<?php

namespace App\Http\Controllers;

use App\Calls\CallLogger;
use App\Calls\CallOutcome;
use App\Models\Lead;
use App\Support\FollowUp;
use App\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "How did the call go?": a call that did not get through, logged in one tap.
 * (A call where they talked goes to the lead's full follow-up form instead.)
 */
class CallController extends Controller
{
    public function store(Request $request, Lead $lead, CallLogger $calls): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'outcome' => ['required', Rule::enum(CallOutcome::class)],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:36000'],
            'note' => ['nullable', 'string', 'max:500'],
            'call_back' => ['nullable', 'in:1h,evening,tomorrow'],
        ]);

        $outcome = CallOutcome::from($data['outcome']);
        $nextTry = match ($data['call_back'] ?? null) {
            '1h' => now()->addHour(),
            'evening' => LocalTime::toUtc(LocalTime::now()->setTime(18, 0)->toDateTimeString()),
            'tomorrow' => LocalTime::toUtc(LocalTime::now()->addDay()->setTime(11, 0)->toDateTimeString()),
            default => null,
        };

        $activity = $calls->log($lead, $request->user(), $outcome, $data['seconds'] ?? null, $data['note'] ?? null, $nextTry);
        $next = $activity->next_follow_up_at ? ' Next try: '.FollowUp::describe($activity->next_follow_up_at)['text'].'.' : '';

        return back()->with('status', "Call logged: {$outcome->label()}.{$next}");
    }
}
