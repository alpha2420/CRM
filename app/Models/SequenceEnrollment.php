<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lead's progress through a sequence. next_step points at the step
 * to run next; next_run_at is when.
 */
class SequenceEnrollment extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'next_step' => 'integer',
            'next_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }
}
