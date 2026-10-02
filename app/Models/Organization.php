<?php

namespace App\Models;

use App\Autopilot\AutopilotSettings;
use App\Billing\Plan;
use App\Billing\PlanCatalog;
use App\Enums\Feature;
use App\Enums\StatusType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
#[Hidden(['api_key_hash'])]
class Organization extends Model
{
    use HasFactory;

    /** Subscription states that still grant access (pending = payment retrying). */
    private const LIVE_SUBSCRIPTION_STATES = ['authenticated', 'active', 'pending'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
            'suspended_at' => 'datetime',
            'require_two_factor' => 'boolean',
            'autopilot' => 'array',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<LeadStatus, $this> */
    public function leadStatuses(): HasMany
    {
        return $this->hasMany(LeadStatus::class);
    }

    /** @return HasMany<Source, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /**
     * The status a new lead starts in: the first open status of the pipeline.
     */
    public function defaultStatus(): ?LeadStatus
    {
        return $this->leadStatuses()
            ->where('type', StatusType::Open)
            ->ordered()
            ->first();
    }

    public function autopilot(): AutopilotSettings
    {
        return new AutopilotSettings($this->autopilot);
    }

    public function hasApiKey(): bool
    {
        return $this->api_key_hash !== null;
    }

    // ---- Plan and access -------------------------------------------------

    public function plan(): Plan
    {
        $catalog = app(PlanCatalog::class);

        return $catalog->find($this->plan) ?? $catalog->trial();
    }

    public function onTrial(): bool
    {
        return $this->plan === PlanCatalog::TRIAL && (bool) $this->trial_ends_at?->isFuture();
    }

    public function trialDaysLeft(): int
    {
        return $this->onTrial() ? (int) ceil(now()->diffInHours($this->trial_ends_at) / 24) : 0;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Paid access lasts while the subscription is live, and after a
     * cancellation until the end of the period already paid for.
     */
    public function hasPaidAccess(): bool
    {
        if ($this->plan === PlanCatalog::TRIAL) {
            return false;
        }

        return in_array($this->subscription_status, self::LIVE_SUBSCRIPTION_STATES, true)
            || (bool) $this->current_period_end?->isFuture();
    }

    public function isActive(): bool
    {
        return ! $this->isSuspended() && ($this->onTrial() || $this->hasPaidAccess());
    }

    public function canUse(Feature $feature): bool
    {
        return $this->isActive() && $this->plan()->allows($feature);
    }

    public function seatsUsed(): int
    {
        return $this->users()->active()->count();
    }

    public function hasFreeSeat(): bool
    {
        return $this->seatsUsed() < $this->plan()->maxUsers;
    }
}
