<?php

namespace App\Jobs;

use App\AdConversions\AdConversions;
use App\Models\AdConversion;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Report one ad result to Meta, in the background. */
class SendAdConversion implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 1800];

    public function __construct(public readonly int $conversionId) {}

    public function handle(AdConversions $conversions, TenantContext $tenant): void
    {
        $conversion = AdConversion::withoutGlobalScopes()->find($this->conversionId);

        if ($conversion === null || $conversion->status === AdConversion::SENT) {
            return;
        }

        $tenant->set($conversion->organization_id);
        $conversions->send($conversion);
    }
}
