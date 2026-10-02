<?php

namespace App\Calls;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\LeadService;
use App\Support\Duration;
use Carbon\CarbonInterface;

/**
 * Records a phone call in the lead's history, from the "How did the call
 * go?" sheet, and plans the next try when the call did not get through.
 */
final class CallLogger
{
    public function __construct(private readonly LeadService $leads) {}

    public function log(Lead $lead, User $user, CallOutcome $outcome, ?int $seconds = null, ?string $note = null, ?CarbonInterface $nextTry = null): LeadActivity
    {
        $line = 'Call: '.$outcome->label().($seconds ? ' ('.Duration::seconds($seconds).')' : '');

        return $this->leads->logActivity($lead, $user, [
            'status_id' => $lead->status_id,
            'note' => trim($line.'. '.($note ?? ''), ' .').'.',
            'next_follow_up_at' => $nextTry ?? $outcome->nextTry() ?? $lead->next_follow_up_at,
            'call_outcome' => $outcome->value,
            'call_seconds' => $seconds,
        ]);
    }
}
