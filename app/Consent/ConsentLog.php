<?php

namespace App\Consent;

use App\Models\ConsentRecord;
use App\Models\Lead;
use App\Models\User;

/**
 * The record of when and how each lead agreed to be contacted or withdrew
 * that agreement. Kept with the lead, so the business can show it later.
 */
final class ConsentLog
{
    public function record(Lead $lead, ConsentAction $action, string $how, ?User $by = null): ConsentRecord
    {
        $record = $lead->consentRecords()->make(['action' => $action, 'how' => mb_substr($how, 0, 200)]);
        $record->organization_id = $lead->organization_id;
        $record->user()->associate($by);
        $record->save();

        return $record;
    }
}
