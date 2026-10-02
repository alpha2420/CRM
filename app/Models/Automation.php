<?php

namespace App\Models;

use App\Automations\Conditions;
use App\Enums\AutomationTrigger;
use App\Observers\AuditTrail;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "When <trigger> (after <trigger_after> days/hours), if <conditions>, then <actions>."
 *
 * conditions: see App\Automations\Conditions
 * actions:    {assign_to?: int, set_status_id?: int, set_priority?: string, whatsapp_template_id?: int,
 *              follow_up_in_hours?: int, notify_user_id?: int, start_sequence_id?: int}
 */
#[Fillable(['name', 'trigger', 'trigger_after', 'conditions', 'actions', 'is_active'])]
#[ObservedBy(AuditTrail::class)]
class Automation extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'trigger' => AutomationTrigger::class,
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'trigger_after' => 'integer',
        ];
    }

    public function condition(string $key): mixed
    {
        return $this->conditions[$key] ?? null;
    }

    public function action(string $key): mixed
    {
        return $this->actions[$key] ?? null;
    }

    /**
     * @param  array{message?: string}  $context
     */
    public function matches(Lead $lead, array $context = []): bool
    {
        return (new Conditions($this->conditions ?? []))->matches($lead, $context);
    }

    /** @return HasMany<AutomationRun, $this> */
    public function runLog(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
