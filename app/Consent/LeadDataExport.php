<?php

namespace App\Consent;

use App\Models\Appointment;
use App\Models\ConsentRecord;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\WhatsAppMessage;
use App\Support\LocalTime;
use Carbon\CarbonInterface;

/**
 * Everything the workspace holds about one lead, for when the lead asks
 * to see it (DPDP right to access).
 */
final class LeadDataExport
{
    /**
     * @return array<string, mixed>
     */
    public function for(Lead $lead): array
    {
        $lead->loadMissing(['organization', 'status', 'source', 'assignee']);
        $fields = CustomField::query()->ordered()->pluck('label', 'key');

        return [
            'exported_at' => $this->time(now()),
            'business' => $lead->organization->name,
            'details' => [
                'name' => $lead->name, 'phone' => $lead->phone, 'email' => $lead->email,
                'company' => $lead->company, 'city' => $lead->city, 'notes' => $lead->notes,
                'stage' => $lead->status?->name, 'source' => $lead->source?->name, 'owner' => $lead->assignee?->name,
                'deal_value' => $lead->value, 'added' => $this->time($lead->created_at),
                'other' => collect($lead->custom_values ?? [])->mapWithKeys(fn ($value, $key) => [$fields[$key] ?? $key => $value])->all(),
            ],
            'consent' => $lead->consentRecords()->oldest('id')->get()->map(fn (ConsentRecord $r) => [
                'when' => $this->time($r->created_at), 'what' => $r->action->label(), 'how' => $r->how,
            ])->all(),
            'history' => $lead->activities()->with('status')->reorder()->oldest('id')->get()->map(fn (LeadActivity $a) => [
                'when' => $this->time($a->created_at), 'stage' => $a->status?->name, 'note' => $a->note,
            ])->all(),
            'whatsapp' => $lead->whatsappMessages()->oldest('id')->get()->map(fn (WhatsAppMessage $m) => [
                'when' => $this->time($m->created_at), 'from' => $m->isInbound() ? 'lead' : 'business', 'text' => $m->preview(),
            ])->all(),
            'meetings' => $lead->appointments()->oldest('starts_at')->get()->map(fn (Appointment $a) => [
                'when' => $this->time($a->starts_at), 'what' => $a->type->label(), 'where' => $a->location, 'status' => $a->status->label(),
            ])->all(),
        ];
    }

    private function time(?CarbonInterface $at): ?string
    {
        return $at ? LocalTime::of($at)->format('Y-m-d H:i') : null;
    }
}
