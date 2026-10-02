<?php

namespace App\Sequences;

use App\Enums\EnrollmentStatus;
use App\Models\SequenceEnrollment;
use App\Services\LeadTimeline;
use App\Support\WorkingHours;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;

/**
 * Runs the sequence steps that are due, a few minutes at a time, inside
 * each workspace's working hours. Every step is written in the lead's
 * history; the last one completes the enrollment.
 */
final class SequenceRunner
{
    /** Steps run per pass, to keep each scheduler run short. */
    private const BATCH = 200;

    public function __construct(
        private readonly LeadTimeline $timeline,
        private readonly TenantContext $tenant,
        private readonly Container $container,
    ) {}

    /**
     * @return int steps run
     */
    public function runDue(): int
    {
        $due = SequenceEnrollment::query()
            ->where('status', EnrollmentStatus::Active)
            ->where('next_run_at', '<=', now())
            ->whereHas('sequence', fn (Builder $q) => $q->where('is_active', true))
            ->with(['lead.organization', 'lead.status', 'sequence.steps.template'])
            ->orderBy('next_run_at')
            ->limit(self::BATCH)
            ->get();
        $ran = 0;

        foreach ($due->groupBy('organization_id') as $organizationId => $enrollments) {
            $organization = $enrollments->first()->lead->organization;

            if (! $organization->isActive() || ! WorkingHours::for($organization)->isOpen()) {
                continue;
            }

            $this->tenant->set((int) $organizationId);
            foreach ($enrollments as $enrollment) {
                $ran += $this->advance($enrollment) ? 1 : 0;
            }
        }

        $this->tenant->clear();

        return $ran;
    }

    /**
     * Run the enrollment's next step and schedule the one after.
     */
    private function advance(SequenceEnrollment $enrollment): bool
    {
        $lead = $enrollment->lead;
        $sequence = $enrollment->sequence;
        $steps = $sequence->steps->values();
        $step = $steps[$enrollment->next_step] ?? null;

        if (! $lead->isOpen() || $step === null) {
            $enrollment->forceFill([
                'status' => $step === null ? EnrollmentStatus::Completed : EnrollmentStatus::Stopped,
                'stop_reason' => $step === null ? null : 'the lead was closed',
                'next_run_at' => null,
            ])->save();

            return false;
        }

        $outcome = $this->container->make($step->action->handler())->run($step, $lead);
        $number = $enrollment->next_step + 1;
        $this->timeline->note($lead, "Sequence “{$sequence->name}”, step {$number} of {$steps->count()}: {$outcome}.");

        $next = $steps[$number] ?? null;
        $enrollment->forceFill([
            'next_step' => $number,
            'next_run_at' => $next ? $enrollment->created_at->copy()->addDays($next->day) : null,
            'status' => $next ? EnrollmentStatus::Active : EnrollmentStatus::Completed,
        ])->save();

        return true;
    }
}
