<?php

namespace App\Broadcasts;

use App\Integrations\WhatsAppService;
use App\Models\Broadcast;
use App\Models\Lead;
use DomainException;

/**
 * Sends a broadcast: the template to every lead in its audience, each as
 * its own queued WhatsApp message, so delivery ticks and replies are
 * tracked like any other message.
 */
final class BroadcastSender
{
    public function __construct(
        private readonly BroadcastAudience $audience,
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function send(Broadcast $broadcast): void
    {
        $template = $broadcast->template;

        if ($template === null || ! $template->isApproved()) {
            $broadcast->forceFill(['status' => Broadcast::FAILED, 'finished_at' => now()])->save();

            return;
        }

        $broadcast->forceFill(['started_at' => now()])->save();
        $sent = 0;
        $skipped = 0;

        $this->audience->query($broadcast->audience)
            ->with('organization')
            ->orderBy('id')
            ->limit(BroadcastAudience::MAX)
            ->get()
            ->each(function (Lead $lead) use ($broadcast, $template, &$sent, &$skipped) {
                $values = $this->whatsapp->defaultParameters($lead, $template->variables);
                foreach ($broadcast->values ?? [] as $index => $value) {
                    if (filled($value) && isset($values[$index - 1])) {
                        $values[$index - 1] = (string) $value;
                    }
                }

                try {
                    // Sent as an automatic message: a broadcast is not a personal first reply.
                    $this->whatsapp->sendTemplate($lead, null, $template, $values, $broadcast->id);
                    $sent++;
                } catch (DomainException) {
                    $skipped++; // asked to stop meanwhile, or WhatsApp was disconnected
                }
            });

        $broadcast->forceFill(['status' => Broadcast::DONE, 'total' => $sent, 'skipped' => $skipped, 'finished_at' => now()])->save();
    }

    /** Meta's approximate charge, so admins know the cost before sending. */
    public static function estimatedCost(?string $category, int $messages): float
    {
        $rates = (array) config('crm.whatsapp_rates_inr');

        return round(($rates[strtoupper((string) $category)] ?? $rates['MARKETING'] ?? 0) * $messages, 2);
    }
}
