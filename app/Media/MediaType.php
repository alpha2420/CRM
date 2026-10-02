<?php

namespace App\Media;

/**
 * The kinds of file a lead can send on WhatsApp. The value matches
 * WhatsApp's message type, which is also stored as the message's type.
 */
enum MediaType: string
{
    case Audio = 'audio';
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
    case Sticker = 'sticker';

    /** What the message is called when it has no caption. */
    public function label(): string
    {
        return match ($this) {
            self::Audio => 'Voice note',
            self::Image => 'Photo',
            self::Video => 'Video',
            self::Document => 'Document',
            self::Sticker => 'Sticker',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Audio => '🎤',
            self::Image, self::Sticker => '📷',
            self::Video => '🎬',
            self::Document => '📄',
        };
    }

    /**
     * File types the browser may open in the page. Anything else, such as
     * a PDF or an HTML file, is only offered as a download.
     */
    public static function opensInline(?string $mime): bool
    {
        return in_array($mime, [
            'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/amr',
            'image/jpeg', 'image/png', 'image/webp',
            'video/mp4', 'video/3gpp',
        ], true);
    }
}
