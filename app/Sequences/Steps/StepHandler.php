<?php

namespace App\Sequences\Steps;

use App\Models\Lead;
use App\Models\SequenceStep;

/**
 * Carries out one kind of sequence step (Strategy pattern): the runner
 * doesn't know or care what a step does.
 */
interface StepHandler
{
    /**
     * Do the step and say what happened, for the lead's history.
     */
    public function run(SequenceStep $step, Lead $lead): string;
}
