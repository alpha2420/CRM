<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\WhatsAppMessage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A lead sent us a WhatsApp message.
 */
final class WhatsAppMessageReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Lead $lead, public readonly WhatsAppMessage $message) {}
}
