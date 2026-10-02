<?php

namespace App\Jobs;

use App\Media\ReceivedMedia;
use App\Models\WhatsAppMessage;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Download a photo, voice note, video or document a lead sent, and write
 * down voice notes. Runs in the background so the webhook answers fast.
 */
class ProcessWhatsAppMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $messageId) {}

    public function handle(ReceivedMedia $media, TenantContext $tenant): void
    {
        $message = WhatsAppMessage::withoutGlobalScopes()->find($this->messageId);

        if ($message === null) {
            return;
        }

        $tenant->set($message->organization_id);
        $media->process($message->load('lead.organization'));
    }
}
