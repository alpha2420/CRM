<?php

namespace App\Models;

use App\Media\MediaType;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('whatsapp_messages')]
#[Fillable(['direction', 'wa_message_id', 'phone', 'type', 'body', 'template_name', 'media_id', 'media_mime', 'media_name', 'media_path', 'transcript', 'transcript_summary', 'status', 'error', 'read_at'])]
class WhatsAppMessage extends Model
{
    use BelongsToOrganization;

    public const IN = 'in';

    public const OUT = 'out';

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::IN;
    }

    /** A photo, voice note, video, document or sticker; null for text. */
    public function mediaType(): ?MediaType
    {
        return MediaType::tryFrom((string) $this->type);
    }

    public function isVoiceNote(): bool
    {
        return $this->mediaType() === MediaType::Audio;
    }

    /**
     * The words that came with a file, if any. The body of a file without
     * a caption is just its label ("Photo") or file name.
     */
    public function caption(): ?string
    {
        $type = $this->mediaType();

        return $type === null || in_array($this->body, [$type->label(), $this->media_name], true) ? null : $this->body;
    }

    /**
     * One line for lists, notifications and the AI: the text, or for a
     * voice note what was said once it has been written down.
     */
    public function preview(): string
    {
        $type = $this->mediaType();

        return match (true) {
            $type === null => (string) $this->body,
            filled($this->transcript) => "{$type->icon()} {$type->label()}: {$this->transcript}",
            default => "{$type->icon()} {$this->body}",
        };
    }
}
