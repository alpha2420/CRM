<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A known lead enquired again (form, ad or API).
 */
final class RepeatEnquiryReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Lead $lead, public readonly string $sourceName) {}
}
