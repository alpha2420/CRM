<?php

namespace App\Media;

use App\Integrations\MetaGraph;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\WhatsAppMessage;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files leads send on WhatsApp, kept on the private disk under
 * whatsapp-media/{workspace}/{lead}/ so they can be removed together with
 * the lead or the workspace. They are only ever served to signed-in team
 * members who may see the lead.
 */
final class MediaLibrary
{
    /** WhatsApp allows up to 100 MB for documents; we keep up to 25 MB. */
    public const MAX_BYTES = 25 * 1024 * 1024;

    private const DISK = 'local';

    public function __construct(private readonly MetaGraph $graph) {}

    /**
     * Download a received file from WhatsApp and keep it.
     *
     * @throws DomainException when the file cannot be kept (too large, wrong host)
     * @throws RequestException when WhatsApp refuses or has a temporary problem
     */
    public function fetch(WhatsAppMessage $message, Integration $whatsapp): void
    {
        $info = $this->graph->media($whatsapp, (string) $message->media_id);

        if (($info['file_size'] ?? 0) > self::MAX_BYTES) {
            throw new DomainException('This file is larger than 25 MB, so it was not saved. Ask the lead to send it another way.');
        }

        $contents = $this->graph->download($whatsapp, (string) ($info['url'] ?? ''));

        if (strlen($contents) > self::MAX_BYTES) {
            throw new DomainException('This file is larger than 25 MB, so it was not saved. Ask the lead to send it another way.');
        }

        $mime = $this->baseMime($info['mime_type'] ?? $message->media_mime);
        $path = $this->folder($message->organization_id, $message->lead_id).'/'.$message->id.'.'.$this->extension($mime);

        Storage::disk(self::DISK)->put($path, $contents);
        $message->update(['media_path' => $path, 'media_mime' => $mime, 'error' => null]);
    }

    public function contents(WhatsAppMessage $message): ?string
    {
        return $message->media_path ? Storage::disk(self::DISK)->get($message->media_path) : null;
    }

    /**
     * Photos, voice notes and videos open in the page; anything else is a
     * download, so a file can never run as part of the CRM.
     */
    public function response(WhatsAppMessage $message): StreamedResponse
    {
        $inline = MediaType::opensInline($message->media_mime);
        $name = $message->media_name ?: Str::slug($message->mediaType()?->label() ?? 'file').'-'.$message->id.'.'.pathinfo((string) $message->media_path, PATHINFO_EXTENSION);

        return Storage::disk(self::DISK)->response(
            (string) $message->media_path,
            $name,
            [
                'Content-Type' => $inline ? (string) $message->media_mime : 'application/octet-stream',
                'Cache-Control' => 'private, max-age=86400',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    public function forgetLead(Lead $lead): void
    {
        Storage::disk(self::DISK)->deleteDirectory($this->folder($lead->organization_id, $lead->id));
    }

    public function forgetOrganization(Organization $organization): void
    {
        Storage::disk(self::DISK)->deleteDirectory("whatsapp-media/{$organization->id}");
    }

    private function folder(int $organizationId, int $leadId): string
    {
        return "whatsapp-media/{$organizationId}/{$leadId}";
    }

    /** "audio/ogg; codecs=opus" → "audio/ogg" */
    private function baseMime(?string $mime): string
    {
        return strtolower(trim(Str::before((string) $mime, ';'))) ?: 'application/octet-stream';
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
