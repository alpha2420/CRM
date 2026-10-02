<?php

namespace App\Consent;

use App\Media\MediaLibrary;
use App\Models\Lead;
use App\Models\User;
use App\Sequences\SequenceEnroller;
use App\Services\AuditLogger;
use App\Services\LeadTimeline;
use Illuminate\Support\Facades\DB;

/**
 * Removes a lead's personal data on request (DPDP right to erasure) or
 * when the workspace's retention period ends. The lead stays as an
 * anonymous row, so reports still count it.
 */
final class LeadEraser
{
    public function __construct(
        private readonly ConsentLog $log,
        private readonly SequenceEnroller $sequences,
        private readonly LeadTimeline $timeline,
        private readonly MediaLibrary $media,
        private readonly AuditLogger $audit,
    ) {}

    public function erase(Lead $lead, string $why, ?User $by = null): void
    {
        if ($lead->erased_at !== null) {
            return;
        }

        DB::transaction(function () use ($lead, $why, $by) {
            $this->sequences->stop($lead, 'personal data erased');
            $lead->activities()->update(['note' => null]);
            $lead->whatsappMessages()->delete();
            $lead->appointments()->update(['location' => null]);

            $lead->forceFill([
                'name' => 'Erased lead',
                'phone' => "erased-{$lead->id}",
                'email' => null, 'company' => null, 'city' => null, 'notes' => null,
                'custom_values' => null, 'ai_insight' => null, 'ai_insight_at' => null,
                'opted_out_at' => $lead->opted_out_at ?? now(),
                'next_follow_up_at' => null,
                'erased_at' => now(),
            ])->saveQuietly();

            $this->log->record($lead, ConsentAction::Withdrawn, "Personal data erased: {$why}", $by);
            $this->timeline->note($lead, "Personal data erased ({$why}).", $by);
        });

        $this->media->forgetLead($lead);
        $this->audit->log('lead.erased', "Erased the personal data of lead #{$lead->id} ({$why})", $lead, $by);
    }
}
