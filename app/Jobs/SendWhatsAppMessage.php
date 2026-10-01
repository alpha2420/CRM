<?php

namespace App\Jobs;

use App\Integrations\WhatsAppService;
use App\Models\WhatsAppMessage;
use App\Tenancy\OrganizationScope;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60];

    /**
     * @param  array{language?: string, parameters?: list<string>}  $templatePayload
     */
    public function __construct(public readonly int $messageId, public readonly array $templatePayload = []) {}

    public function handle(WhatsAppService $whatsapp, TenantContext $tenant): void
    {
        $message = $this->message();

        if ($message === null || $message->status !== 'queued') {
            return;
        }

        $tenant->set($message->organization_id);
        $whatsapp->deliver($message, $this->templatePayload);
    }

    public function failed(?Throwable $exception): void
    {
        $this->message()?->update(['status' => 'failed', 'error' => 'WhatsApp could not be reached. Please try again.']);
    }

    private function message(): ?WhatsAppMessage
    {
        return WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->with('lead.organization')->find($this->messageId);
    }
}
