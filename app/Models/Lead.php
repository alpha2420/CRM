<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\LeadStage;
use App\Enums\Priority;
use App\Enums\StatusType;
use App\Observers\LeadObserver;
use App\Support\LocalTime;
use App\Tenancy\BelongsToOrganization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name', 'phone', 'email', 'company', 'city', 'source_id', 'status_id',
    'assigned_to', 'value', 'priority', 'notes', 'next_follow_up_at', 'custom_values',
])]
#[ObservedBy(LeadObserver::class)]
class Lead extends Model
{
    use BelongsToOrganization, HasFactory;

    /**
     * Set on the instance an automation saves, so the resulting events do
     * not start other automations (which could loop).
     */
    public bool $changedByAutomation = false;

    /** Same default as the database, so a new lead has it before it's reloaded. */
    protected $attributes = [
        'priority' => 'medium',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'assigned_to' => 'integer',
            'priority' => Priority::class,
            'score' => 'integer',
            'value' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'reminded_at' => 'datetime',
            'first_contacted_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'erased_at' => 'datetime',
            'escalated_at' => 'datetime',
            'reengaged_at' => 'datetime',
            'win_back_at' => 'datetime',
            'lost_reason_id' => 'integer',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'closed_at' => 'datetime',
            'custom_values' => 'array',
            'ai_insight' => 'array',
            'ai_insight_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LeadStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class);
    }

    /** @return BelongsTo<LostReason, $this> */
    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<LeadActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest()->latest('id');
    }

    /** @return HasMany<WhatsAppMessage, $this> */
    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /** @return HasMany<ConsentRecord, $this> */
    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class)->latest('id');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('starts_at');
    }

    /** @return HasOne<SequenceEnrollment, $this> */
    public function activeEnrollment(): HasOne
    {
        return $this->hasOne(SequenceEnrollment::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('status', EnrollmentStatus::Active));
    }

    /** @return HasOne<WhatsAppMessage, $this> */
    public function latestWhatsAppMessage(): HasOne
    {
        return $this->hasOne(WhatsAppMessage::class)->latestOfMany();
    }

    /**
     * WhatsApp allows free-form replies only within 24 hours of the lead's
     * last message; outside that window a pre-approved template is needed.
     */
    /** "Priya" from "Mrs. Priya Sharma": for greetings and short mentions. */
    public function firstName(): string
    {
        $titles = ['mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'shri', 'sri', 'smt', 'kumari'];

        foreach (preg_split('/\s+/', trim($this->name)) ?: [] as $word) {
            if (! in_array(rtrim(mb_strtolower($word), '.'), $titles, true)) {
                return $word;
            }
        }

        return trim($this->name);
    }

    public function whatsappWindowOpen(): bool
    {
        return (bool) $this->last_inbound_at?->gt(now()->subDay());
    }

    public function isOpen(): bool
    {
        return $this->status?->type === StatusType::Open;
    }

    /** Leads in an open stage (not won or lost). */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status_id', self::statusIds(StatusType::Open));
    }

    /** Leads who have not asked to stop getting messages. */
    #[Scope]
    protected function contactable(Builder $query): void
    {
        $query->whereNull('opted_out_at');
    }

    /** No follow-up logged and no WhatsApp message since $cutoff. */
    #[Scope]
    protected function quietSince(Builder $query, CarbonInterface $cutoff): void
    {
        $query
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->whereNull('last_activity_at')->where('created_at', '<', $cutoff))
                ->orWhere('last_activity_at', '<', $cutoff))
            ->where(fn (Builder $q) => $q->whereNull('last_message_at')->orWhere('last_message_at', '<', $cutoff));
    }

    /**
     * Admins see every lead of their organization; agents only their own.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where('assigned_to', $user->id);
        }
    }

    #[Scope]
    protected function inStage(Builder $query, LeadStage $stage): void
    {
        $cutoff = now()->subDays(config('crm.dormant_after_days'));

        match ($stage) {
            LeadStage::All => null,
            LeadStage::Won => $query->whereIn('status_id', self::statusIds(StatusType::Won)),
            LeadStage::Lost => $query->whereIn('status_id', self::statusIds(StatusType::Lost)),
            LeadStage::Fresh => $query->whereIn('status_id', self::statusIds(StatusType::Open))
                ->whereNull('last_activity_at')
                ->where('created_at', '>=', $cutoff),
            LeadStage::Working => $query->whereIn('status_id', self::statusIds(StatusType::Open))
                ->where('last_activity_at', '>=', $cutoff),
            LeadStage::Dormant => $query->whereIn('status_id', self::statusIds(StatusType::Open))
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q) => $q->whereNull('last_activity_at')->where('created_at', '<', $cutoff))
                    ->orWhere('last_activity_at', '<', $cutoff)),
            LeadStage::Due => $query->whereIn('status_id', self::statusIds(StatusType::Open))
                ->where('next_follow_up_at', '<=', LocalTime::endOfToday()),
        };
    }

    /**
     * @param  array{q?: ?string, status_id?: ?int, source_id?: ?int, assigned_to?: ?int, priority?: ?string, from?: ?string, to?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%")))
            ->when($filters['status_id'] ?? null, fn (Builder $q, $id) => $q->where('status_id', $id))
            ->when($filters['source_id'] ?? null, fn (Builder $q, $id) => $q->where('source_id', $id))
            ->when($filters['assigned_to'] ?? null, fn (Builder $q, $id) => $q->where('assigned_to', $id))
            ->when($filters['priority'] ?? null, fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when($filters['from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', LocalTime::dayBoundary($date)))
            ->when($filters['to'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<=', LocalTime::dayBoundary($date, end: true)));
    }

    private static function statusIds(StatusType $type): Builder
    {
        return LeadStatus::query()->where('type', $type)->select('id');
    }
}
