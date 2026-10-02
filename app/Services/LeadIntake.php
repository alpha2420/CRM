<?php

namespace App\Services;

use App\Consent\ConsentAction;
use App\Consent\ConsentLog;
use App\Events\RepeatEnquiryReceived;
use App\Models\Lead;
use App\Models\Organization;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Entry point for leads arriving from outside: the API, the website form,
 * WhatsApp, Facebook and Google lead ads. A known phone number is not
 * rejected; the new enquiry is added to that lead's history instead, so
 * nothing a customer sends is lost.
 */
final class LeadIntake
{
    public function __construct(
        private readonly LeadService $leads,
        private readonly ConsentLog $consent,
    ) {}

    /**
     * @param  array{name: string, phone: string, email?: ?string, company?: ?string, city?: ?string, notes?: ?string}  $data
     */
    public function capture(Organization $organization, array $data, string $sourceName, ?int $sourceId = null): IntakeResult
    {
        $data['phone'] = PhoneNumber::normalize($data['phone']);

        return DB::transaction(function () use ($organization, $data, $sourceName, $sourceId) {
            $existing = $organization->leads()->where('phone', $data['phone'])->lockForUpdate()->first();

            if ($existing !== null) {
                $this->recordRepeatEnquiry($existing, $sourceName, $data['notes'] ?? null);

                return new IntakeResult($existing, created: false);
            }

            $data['source_id'] = $sourceId ?? $organization->sources()->firstOrCreate(['name' => $sourceName])->id;
            $lead = $this->leads->create($organization, $data);

            // They reached out themselves, which is their agreement to be contacted about it.
            $this->consent->record($lead, ConsentAction::Given, "Enquired via {$sourceName}");

            return new IntakeResult($lead, created: true);
        });
    }

    private function recordRepeatEnquiry(Lead $lead, string $sourceName, ?string $notes): void
    {
        $activity = $lead->activities()->make([
            'status_id' => $lead->status_id,
            'note' => trim("New enquiry via {$sourceName}. ".($notes ?? '')),
        ]);
        $activity->organization_id = $lead->organization_id;
        $activity->save();

        $lead->forceFill(['last_activity_at' => $activity->created_at])->saveQuietly();

        RepeatEnquiryReceived::dispatch($lead, $sourceName);
    }
}
