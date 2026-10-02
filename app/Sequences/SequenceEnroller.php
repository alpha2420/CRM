<?php

namespace App\Sequences;

use App\Enums\EnrollmentStatus;
use App\Models\Lead;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Services\LeadTimeline;
use Illuminate\Support\Facades\DB;

/**
 * Starts and stops sequences for a lead. A lead is in at most one
 * sequence at a time: starting a new one stops the old one.
 */
final class SequenceEnroller
{
    public function __construct(private readonly LeadTimeline $timeline) {}

    public function enroll(Lead $lead, Sequence $sequence, ?User $by = null): SequenceEnrollment
    {
        return DB::transaction(function () use ($lead, $sequence, $by) {
            $this->stop($lead, "Replaced by “{$sequence->name}”");

            $first = $sequence->steps()->first();
            $enrollment = $sequence->enrollments()->make([]);
            $enrollment->forceFill([
                'organization_id' => $lead->organization_id,
                'lead_id' => $lead->id,
                'enrolled_by' => $by?->id,
                'next_step' => 0,
                'next_run_at' => $first ? now()->addDays($first->day) : null,
                'status' => $first ? EnrollmentStatus::Active : EnrollmentStatus::Completed,
            ])->save();

            $this->timeline->note($lead, "Started the “{$sequence->name}” sequence".($by ? " ({$by->name})" : '').'.');

            return $enrollment;
        });
    }

    /**
     * The sequence the lead is in right now, if any.
     */
    public function current(Lead $lead): ?SequenceEnrollment
    {
        return SequenceEnrollment::query()
            ->where('lead_id', $lead->id)
            ->where('status', EnrollmentStatus::Active)
            ->with('sequence')
            ->first();
    }

    /**
     * Stop the lead's running sequence, if any.
     */
    public function stop(Lead $lead, string $reason): bool
    {
        $enrollment = $this->current($lead);

        if ($enrollment === null) {
            return false;
        }

        $enrollment->forceFill(['status' => EnrollmentStatus::Stopped, 'stop_reason' => $reason, 'next_run_at' => null])->save();
        $this->timeline->note($lead, "Stopped the “{$enrollment->sequence->name}” sequence: {$reason}.");

        return true;
    }
}
