<?php

namespace App\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadTimeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Books meetings with a lead and records how they went. A booking becomes
 * the lead's next follow-up (unless an earlier one is planned).
 */
final class AppointmentScheduler
{
    public function __construct(private readonly LeadTimeline $timeline) {}

    /**
     * @param  array{type: string, starts_at: \DateTimeInterface, location?: ?string}  $data
     */
    public function book(Lead $lead, array $data, User $by): Appointment
    {
        return DB::transaction(function () use ($lead, $data, $by) {
            $appointment = $lead->appointments()->make($data + ['user_id' => $lead->assigned_to ?? $by->id]);
            $appointment->organization_id = $lead->organization_id;
            $appointment->save();

            if ($lead->next_follow_up_at === null || $lead->next_follow_up_at->gt($appointment->starts_at)) {
                $lead->forceFill(['next_follow_up_at' => $appointment->starts_at])->save();
            }

            $this->timeline->note($lead, 'Booked a '.Str::lcfirst($appointment->describe()).'.', $by);

            return $appointment;
        });
    }

    /**
     * Done, no-show or cancelled. After a no-show the lead is due now, so
     * someone reschedules.
     */
    public function close(Appointment $appointment, AppointmentStatus $outcome, User $by): void
    {
        DB::transaction(function () use ($appointment, $outcome, $by) {
            $appointment->forceFill(['status' => $outcome])->save();
            $lead = $appointment->lead;

            if ($outcome === AppointmentStatus::NoShow) {
                $lead->forceFill(['next_follow_up_at' => now()])->save();
            }

            $this->timeline->note($lead, $appointment->describe().': '.Str::lower($outcome->label()).'.', $by);
        });
    }
}
