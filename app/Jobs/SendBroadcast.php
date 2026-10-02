<?php

namespace App\Jobs;

use App\Broadcasts\BroadcastSender;
use App\Models\Broadcast;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Send a broadcast in the background. */
class SendBroadcast implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public readonly int $broadcastId) {}

    public function handle(BroadcastSender $sender, TenantContext $tenant): void
    {
        $broadcast = Broadcast::withoutGlobalScopes()->find($this->broadcastId);

        if ($broadcast === null || $broadcast->started_at !== null) {
            return; // gone, or already sent
        }

        $tenant->set($broadcast->organization_id);
        $sender->send($broadcast->load('template'));
    }
}
