<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone recorded a follow-up (call, note, stage move) on a lead.
 */
final class FollowUpLogged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Lead $lead, public readonly LeadActivity $activity) {}
}
