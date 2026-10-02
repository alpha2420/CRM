<?php

namespace App\Http\Controllers;

use App\Appointments\AppointmentScheduler;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Lead;
use App\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Book a meeting with a lead, and record how it went.
 */
class AppointmentController extends Controller
{
    public function store(Request $request, Lead $lead, AppointmentScheduler $scheduler): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'starts_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:150'],
        ]);
        $startsAt = LocalTime::toUtc($data['starts_at']);

        if ($startsAt->isPast()) {
            return back()->withErrors(['starts_at' => 'Pick a time in the future.'])->withInput();
        }

        $appointment = $scheduler->book($lead, ['type' => $data['type'], 'starts_at' => $startsAt, 'location' => $data['location'] ?? null], $request->user());

        return back()->with('status', "{$appointment->type->label()} booked for {$appointment->when()}.");
    }

    public function update(Request $request, Appointment $appointment, AppointmentScheduler $scheduler): RedirectResponse
    {
        Gate::authorize('update', $appointment->lead);

        $data = $request->validate(['outcome' => ['required', Rule::enum(AppointmentStatus::class)->except([AppointmentStatus::Scheduled])]]);
        abort_unless($appointment->isScheduled(), 422, 'This meeting already has an outcome.');

        $scheduler->close($appointment, AppointmentStatus::from($data['outcome']), $request->user());

        return back()->with('status', 'Meeting updated.');
    }
}
