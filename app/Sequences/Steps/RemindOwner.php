<?php

namespace App\Sequences\Steps;

use App\Models\Lead;
use App\Models\SequenceStep;

/**
 * Make the lead due now, so its owner gets the usual follow-up reminder
 * with the step's note in the lead's history.
 */
final class RemindOwner implements StepHandler
{
    public function run(SequenceStep $step, Lead $lead): string
    {
        $lead->forceFill(['next_follow_up_at' => now()])->save();

        return "reminded the owner: {$step->note}";
    }
}
