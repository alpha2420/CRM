<?php

namespace App\Services;

final readonly class ImportResult
{
    /**
     * @param  list<string>  $errors  one message per skipped row
     */
    public function __construct(
        public int $created,
        public int $skipped,
        public array $errors,
    ) {}
}
