<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Support\LocalTime;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A meeting, site visit, demo or call booked with a lead.
 */
#[Fillable(['type', 'starts_at', 'location', 'user_id'])]
class Appointment extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'user_id' => 'integer',
            'lead_reminded_day_at' => 'datetime',
            'lead_reminded_hour_at' => 'datetime',
            'owner_reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === AppointmentStatus::Scheduled;
    }

    /** Booked, and its time has passed: ask how it went. */
    public function awaitsOutcome(): bool
    {
        return $this->isScheduled() && $this->starts_at->isPast();
    }

    /** "Site visit on Sat 4 Oct, 11:00 at Pune office" */
    public function describe(): string
    {
        return $this->type->label().' on '.$this->when().($this->location ? " at {$this->location}" : '');
    }

    /** "Sat 4 Oct, 11:00", in the workspace's time zone. */
    public function when(): string
    {
        return LocalTime::of($this->starts_at)->format('D j M, H:i');
    }
}
