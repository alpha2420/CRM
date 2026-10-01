<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class LeadStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly ?int $previousStatusId,
        public readonly ?User $actor,
    ) {}
}
