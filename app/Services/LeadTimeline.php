<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;

/**
 * Lines in a lead's history for things other than a logged follow-up:
 * what Autopilot or a sequence did, or a booking someone made.
 */
final class LeadTimeline
{
    /**
     * @param  User|null  $by  the person who did it; null for the system
     */
    public function note(Lead $lead, string $text, ?User $by = null): LeadActivity
    {
        $activity = $lead->activities()->make(['status_id' => $lead->status_id, 'note' => $text]);
        $activity->organization_id = $lead->organization_id;
        $activity->user()->associate($by);
        $activity->save();

        return $activity;
    }
}
