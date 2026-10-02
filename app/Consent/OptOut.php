<?php

namespace App\Consent;

use App\Models\Lead;
use App\Models\User;
use App\Sequences\SequenceEnroller;
use App\Services\LeadTimeline;

/**
 * A lead can ask to stop getting messages (by replying STOP, or through a
 * team member) and ask to start again. While opted out, nothing automatic
 * contacts them and templates cannot be sent; the team can still answer
 * when the lead writes first.
 */
final class OptOut
{
    /** Whole-message replies that mean "stop", in English, Hinglish and Hindi. */
    public const STOP_WORDS = ['stop', 'stop all', 'unsubscribe', 'opt out', 'optout', 'band karo', 'mat bhejo', 'message mat karo', 'रुको', 'बंद करो', 'बंद'];

    /** Whole-message replies that mean "start again". */
    public const START_WORDS = ['start', 'subscribe', 'unstop', 'resume', 'shuru karo'];

    public function __construct(
        private readonly ConsentLog $log,
        private readonly SequenceEnroller $sequences,
        private readonly LeadTimeline $timeline,
    ) {}

    /**
     * What a message means if it is only a stop or start word, otherwise
     * null. "Stop by the office tomorrow" is not an opt-out.
     */
    public static function intentOf(string $message): ?ConsentAction
    {
        $text = trim((string) preg_replace(['/[^\p{L}\p{M}\p{N} ]+/u', '/\s+/u'], ['', ' '], mb_strtolower($message)));

        return match (true) {
            in_array($text, self::STOP_WORDS, true) => ConsentAction::Withdrawn,
            in_array($text, self::START_WORDS, true) => ConsentAction::Given,
            default => null,
        };
    }

    public function withdraw(Lead $lead, string $how, ?User $by = null): void
    {
        if ($lead->opted_out_at !== null) {
            return;
        }

        $lead->forceFill(['opted_out_at' => now()])->saveQuietly();
        $this->log->record($lead, ConsentAction::Withdrawn, $how, $by);
        $this->sequences->stop($lead, 'they asked not to get messages');
        $this->timeline->note($lead, "Asked not to get messages ({$how}). Automatic messages and templates are now off.", $by);
    }

    public function restore(Lead $lead, string $how, ?User $by = null): void
    {
        if ($lead->opted_out_at === null) {
            return;
        }

        $lead->forceFill(['opted_out_at' => null])->saveQuietly();
        $this->log->record($lead, ConsentAction::Given, $how, $by);
        $this->timeline->note($lead, "Agreed to get messages again ({$how}).", $by);
    }
}
