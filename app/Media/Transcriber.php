<?php

namespace App\Media;

use App\Ai\AssistantException;

/**
 * Turns a voice note into text. Implemented with Gemini, which can listen
 * to audio, and by a fake in tests.
 */
interface Transcriber
{
    public function isConfigured(): bool;

    /**
     * @throws AssistantException when no usable transcript comes back
     */
    public function transcribe(string $audio, string $mimeType): Transcript;
}
