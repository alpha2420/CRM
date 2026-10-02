<?php

namespace App\Sequences\Steps;

use App\Integrations\WhatsAppService;
use App\Models\Lead;
use App\Models\SequenceStep;
use DomainException;

/** Send the step's approved WhatsApp template to the lead. */
final class SendTemplate implements StepHandler
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    public function run(SequenceStep $step, Lead $lead): string
    {
        $template = $step->template;

        if ($template === null || ! $template->isApproved()) {
            return 'skipped a WhatsApp message: its template is no longer approved';
        }

        try {
            $this->whatsapp->sendTemplate($lead, null, $template, $this->whatsapp->defaultParameters($lead, $template->variables));
        } catch (DomainException) {
            return "skipped “{$template->name}”: WhatsApp is not connected";
        }

        return "sent the “{$template->name}” WhatsApp template";
    }
}
