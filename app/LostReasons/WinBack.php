<?php

namespace App\LostReasons;

use App\Autopilot\Autopilot;
use App\Enums\StatusType;
use App\Integrations\WhatsAppService;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\WhatsAppTemplate;
use App\Support\WorkingHours;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Win-back (Settings → Autopilot): a lost lead whose reason has a delay
 * ("Price too high: 30 days") is reopened for its owner once that many
 * days have passed, with the chosen WhatsApp template. Once per lead.
 */
final class WinBack
{
    /** Leads handled per workspace per run. */
    private const BATCH = 200;

    public function __construct(
        private readonly Autopilot $autopilot,
        private readonly WhatsAppService $whatsapp,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return int leads won back
     */
    public function run(): int
    {
        $total = 0;

        foreach (Organization::query()->whereNull('suspended_at')->cursor() as $organization) {
            $settings = $organization->autopilot();

            if (! $settings->on('win_back') || ! $organization->isActive() || ! WorkingHours::for($organization)->isOpen()) {
                continue;
            }

            $this->tenant->set($organization->id);
            $template = WhatsAppTemplate::query()->find($settings->get('win_back_template_id'));
            $total += $this->winBack($template?->isApproved() ? $template : null);
        }

        $this->tenant->clear();

        return $total;
    }

    private function winBack(?WhatsAppTemplate $template): int
    {
        $due = Lead::query()
            ->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Lost)->select('id'))
            ->whereNull('win_back_at')
            ->contactable()
            ->whereHas('lostReason', fn (Builder $q) => $q->whereNotNull('win_back_after_days'))
            ->with(['lostReason', 'organization'])
            ->limit(self::BATCH)
            ->get()
            ->filter(fn (Lead $lead) => $lead->closed_at?->lte(now()->subDays($lead->lostReason->win_back_after_days)));

        foreach ($due as $lead) {
            $days = $lead->lostReason->win_back_after_days;
            $reason = $lead->lostReason->name;

            $lead->forceFill(['win_back_at' => now()])->saveQuietly();
            $this->autopilot->reopen($lead, "win-back, {$days} days after it was lost ({$reason})");

            if ($template !== null) {
                try {
                    $this->whatsapp->sendTemplate($lead, null, $template, $this->whatsapp->defaultParameters($lead, $template->variables));
                } catch (DomainException) {
                    // WhatsApp not connected: the owner still gets the lead back.
                }
            }
        }

        return $due->count();
    }
}
