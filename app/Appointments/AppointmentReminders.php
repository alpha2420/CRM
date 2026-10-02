<?php

namespace App\Appointments;

use App\Enums\AppointmentStatus;
use App\Integrations\WhatsAppService;
use App\Models\Appointment;
use App\Models\WhatsAppTemplate;
use App\Notifications\AppointmentReminderNotification;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Str;

/**
 * Reminders before a booked meeting: the lead gets the chosen WhatsApp
 * template a day before and an hour before; the owner gets a nudge an
 * hour before. Each goes out once.
 */
final class AppointmentReminders
{
    private const DAY_BEFORE_MINUTES = 24 * 60;

    private const HOUR_BEFORE_MINUTES = 60;

    /** Too close to the start for a "see you tomorrow" message. */
    private const DAY_REMINDER_CUTOFF_MINUTES = 3 * 60;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return int reminders sent
     */
    public function sendDue(): int
    {
        $sent = 0;
        $upcoming = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled)
            ->whereBetween('starts_at', [now(), now()->addMinutes(self::DAY_BEFORE_MINUTES)])
            ->with(['lead.organization', 'owner'])
            ->get();

        foreach ($upcoming->groupBy('organization_id') as $organizationId => $appointments) {
            $organization = $appointments->first()->lead->organization;
            if (! $organization->isActive()) {
                continue;
            }

            $this->tenant->set((int) $organizationId);
            $settings = $organization->autopilot();
            $template = $settings->on('meeting_reminders') ? WhatsAppTemplate::query()->find($settings->get('meeting_template_id')) : null;

            foreach ($appointments as $appointment) {
                $sent += $this->remind($appointment, $template?->isApproved() ? $template : null);
            }
        }

        $this->tenant->clear();

        return $sent;
    }

    private function remind(Appointment $appointment, ?WhatsAppTemplate $template): int
    {
        $minutes = now()->diffInMinutes($appointment->starts_at, true);
        $sent = 0;

        if ($template && $appointment->lead_reminded_day_at === null && $minutes > self::DAY_REMINDER_CUTOFF_MINUTES) {
            $sent += $this->messageLead($appointment, $template) ? 1 : 0;
            $appointment->lead_reminded_day_at = now();
        }

        if ($minutes <= self::HOUR_BEFORE_MINUTES) {
            if ($template && $appointment->lead_reminded_hour_at === null) {
                $sent += $this->messageLead($appointment, $template) ? 1 : 0;
                $appointment->lead_reminded_hour_at = now();
            }

            if ($appointment->owner_reminded_at === null && $appointment->owner?->is_active) {
                $appointment->owner->notify(new AppointmentReminderNotification($appointment));
                $appointment->owner_reminded_at = now();
                $sent++;
            }
        }

        $appointment->save();

        return $sent;
    }

    /**
     * Template variables: {{1}} first name, {{2}} date and time, {{3}} place.
     */
    private function messageLead(Appointment $appointment, WhatsAppTemplate $template): bool
    {
        $lead = $appointment->lead;
        $values = [Str::before(trim($lead->name), ' '), $appointment->when(), $appointment->location ?: $lead->organization->name];

        try {
            $this->whatsapp->sendTemplate($lead, null, $template, array_slice($values, 0, $template->variables));

            return true;
        } catch (DomainException) {
            return false; // WhatsApp not connected: the owner reminder still goes out
        }
    }
}
