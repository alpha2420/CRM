<?php

namespace App\Listeners;

use App\Events\LeadStatusChanged;
use App\Events\WhatsAppMessageReceived;
use App\Models\LeadStatus;
use App\Sequences\SequenceEnroller;

/**
 * A sequence is for leads who haven't engaged yet: it stops when the lead
 * replies (if the sequence says so) and always when the lead is closed.
 */
class StopSequences
{
    public function __construct(private readonly SequenceEnroller $enroller) {}

    public function handleReply(WhatsAppMessageReceived $event): void
    {
        if ($this->enroller->current($event->lead)?->sequence->stop_on_reply) {
            $this->enroller->stop($event->lead, 'the lead replied');
        }
    }

    public function handleStatusChanged(LeadStatusChanged $event): void
    {
        if (! LeadStatus::query()->findOrFail($event->lead->status_id)->isOpen()) {
            $this->enroller->stop($event->lead, 'the lead was closed');
        }
    }
}
