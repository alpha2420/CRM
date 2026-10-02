<?php

namespace App\Services;

use App\Consent\ConsentAction;
use App\Consent\ConsentLog;
use App\Events\RepeatEnquiryReceived;
use App\Models\Lead;
use App\Models\Organization;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
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
     * @param  array{name: string, phone: string, email?: ?string, company?: ?string, city?: ?string, notes?: ?string, campaign?: string, ad_id?: string, click_id?: string, click_type?: string}  $data
     */
    public function capture(Organization $organization, array $data, string $sourceName, ?int $sourceId = null): IntakeResult
    {
        $data['phone'] = PhoneNumber::international($data['phone'], $organization->countryCode());

        return DB::transaction(function () use ($organization, $data, $sourceName, $sourceId) {
            $existing = $organization->leads()->where('phone', $data['phone'])->lockForUpdate()->first();

            if ($existing !== null) {
                $this->keepFirstTouch($existing, $data);
                $this->recordRepeatEnquiry($existing, $sourceName, $data['notes'] ?? null, $data['campaign'] ?? null);

                return new IntakeResult($existing, created: false);
            }

            $data['source_id'] = $sourceId ?? $organization->sources()->firstOrCreate(['name' => $sourceName])->id;
            $lead = $this->leads->create($organization, $data);

            // They reached out themselves, which is their agreement to be contacted about it.
            $this->consent->record($lead, ConsentAction::Given, "Enquired via {$sourceName}");

            return new IntakeResult($lead, created: true);
        });
    }

    /**
     * A lead first added by hand (or from an organic source) that later
     * comes through a campaign gets that campaign; one that already has a
     * campaign keeps it.
     *
     * @param  array<string, mixed>  $data
     */
    public function keepFirstTouch(Lead $lead, array $data): void
    {
        if ($lead->campaign === null && filled($data['campaign'] ?? null)) {
            $lead->forceFill(Arr::only($data, ['campaign', 'ad_id', 'click_id', 'click_type']))->saveQuietly();
        }
    }

    private function recordRepeatEnquiry(Lead $lead, string $sourceName, ?string $notes, ?string $campaign): void
    {
        $via = $campaign ? "{$sourceName} (campaign “{$campaign}”)" : $sourceName;
        $activity = $lead->activities()->make([
            'status_id' => $lead->status_id,
            'note' => trim("New enquiry via {$via}. ".($notes ?? '')),
        ]);
        $activity->organization_id = $lead->organization_id;
        $activity->save();

        $lead->forceFill(['last_activity_at' => $activity->created_at])->saveQuietly();

        RepeatEnquiryReceived::dispatch($lead, $sourceName);
    }
}
