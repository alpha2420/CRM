<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;

/**
 * Lines in a lead's history written by the system rather than a person
 * (Autopilot, sequences), so people can always see what happened and why.
 */
final class LeadTimeline
{
    public function note(Lead $lead, string $text): LeadActivity
    {
        $activity = $lead->activities()->make(['status_id' => $lead->status_id, 'note' => $text]);
        $activity->organization_id = $lead->organization_id;
        $activity->save();

        return $activity;
    }
}
