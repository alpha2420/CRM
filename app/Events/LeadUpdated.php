<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead was saved with changes. $changed lists the attribute names, so
 * listeners can react only to what they care about.
 */
final class LeadUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $changed
     */
    public function __construct(public readonly Lead $lead, public readonly array $changed) {}
}
