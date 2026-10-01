<?php

namespace App\Services;

use App\Models\Lead;

final readonly class IntakeResult
{
    public function __construct(public Lead $lead, public bool $created) {}
}
