<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A WhatsApp message was queued to a lead; $sender is null when the CRM sent it on its own.
 */
final class WhatsAppMessageSent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Lead $lead, public readonly WhatsAppMessage $message, public readonly ?User $sender) {}
}
