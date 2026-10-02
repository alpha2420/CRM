<?php

namespace App\Media;

/**
 * What a lead said in a voice note, and in one line what they want.
 */
final readonly class Transcript
{
    public function __construct(
        public string $text,
        public string $summary,
    ) {}
}
