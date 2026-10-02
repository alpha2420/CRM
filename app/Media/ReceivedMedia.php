<?php

namespace App\Media;

use App\Ai\AiUsage;
use App\Ai\AssistantException;
use App\Enums\Feature;
use App\Integrations\WhatsAppService;
use App\Models\Organization;
use App\Models\WhatsAppMessage;
use DomainException;
use Illuminate\Http\Client\RequestException;

/**
 * What happens after a lead sends a file: keep a copy, and when Autopilot's
 * "Write down voice notes" is on, turn a voice note into text so the team
 * can read it instead of listening.
 */
final class ReceivedMedia
{
    public function __construct(
        private readonly MediaLibrary $library,
        private readonly Transcriber $transcriber,
        private readonly AiUsage $usage,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /** Why voice notes cannot be written down for this workspace, or null when they can. */
    public function transcriptionUnavailable(Organization $organization): ?string
    {
        return match (true) {
            ! $this->transcriber->isConfigured() => 'Needs a Gemini API key on this server (GEMINI_API_KEY).',
            ! $organization->canUse(Feature::Ai) => 'Needs the AI assistant, which comes with the Pro plan.',
            default => null,
        };
    }

    /**
     * @throws RequestException when WhatsApp has a temporary problem, so the job is retried
     */
    public function process(WhatsAppMessage $message): void
    {
        $organization = $message->lead->organization;
        $whatsapp = $this->whatsapp->integrationFor($organization);

        if ($whatsapp === null || $message->media_id === null) {
            return;
        }

        if ($message->media_path === null) {
            try {
                $this->library->fetch($message, $whatsapp);
            } catch (DomainException $e) {
                $message->update(['error' => $e->getMessage()]);

                return;
            } catch (RequestException $e) {
                if ($e->response->serverError()) {
                    throw $e;
                }
                $message->update(['error' => 'This file could not be downloaded from WhatsApp.']);

                return;
            }
        }

        if ($this->shouldTranscribe($message, $organization)) {
            $this->transcribe($message, $organization);
        }
    }

    private function shouldTranscribe(WhatsAppMessage $message, Organization $organization): bool
    {
        return $message->isVoiceNote()
            && $message->transcript === null
            && $organization->autopilot()->on('voice_notes')
            && $this->transcriptionUnavailable($organization) === null
            && $this->usage->remaining($organization) > 0;
    }

    private function transcribe(WhatsAppMessage $message, Organization $organization): void
    {
        try {
            $transcript = $this->transcriber->transcribe((string) $this->library->contents($message), (string) $message->media_mime);
        } catch (AssistantException) {
            return; // the voice note can still be played
        }

        $message->update(['transcript' => $transcript->text, 'transcript_summary' => $transcript->summary ?: null]);
        $this->usage->record($organization);
    }
}
