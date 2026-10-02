<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Observers\AuditTrail;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A follow-up sequence: timed steps a lead goes through automatically
 * until it replies, closes or reaches the end.
 */
#[Fillable(['name', 'is_active', 'stop_on_reply'])]
#[ObservedBy(AuditTrail::class)]
class Sequence extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'stop_on_reply' => 'boolean',
        ];
    }

    /** @return HasMany<SequenceStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class)->orderBy('day')->orderBy('id');
    }

    /** @return HasMany<SequenceEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class);
    }

    /** @return HasMany<SequenceEnrollment, $this> */
    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', EnrollmentStatus::Active);
    }

    /**
     * Replace the steps with the given ones (the edit form sends them all).
     * Leads already in the sequence carry on from their current step number.
     *
     * @param  list<array{day: int, action: string, whatsapp_template_id: ?int, note: ?string}>  $steps
     */
    public function replaceSteps(array $steps): void
    {
        DB::transaction(function () use ($steps) {
            $this->steps()->delete();

            foreach ($steps as $attributes) {
                // Stamped from the sequence, so this works outside a web request too.
                $step = $this->steps()->make($attributes);
                $step->organization_id = $this->organization_id;
                $step->save();
            }
        });
    }
}
