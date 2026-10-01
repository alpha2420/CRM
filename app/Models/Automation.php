<?php

namespace App\Models;

use App\Enums\AutomationTrigger;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * conditions: {source_id?: int, status_id?: int}
 * actions:    {assign_to?: int, set_status_id?: int, whatsapp_template_id?: int,
 *              follow_up_in_hours?: int, notify_user_id?: int}
 */
#[Fillable(['name', 'trigger', 'conditions', 'actions', 'is_active'])]
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

    public function matches(Lead $lead): bool
    {
        return ($this->condition('source_id') === null || (int) $this->condition('source_id') === $lead->source_id)
            && ($this->condition('status_id') === null || (int) $this->condition('status_id') === $lead->status_id);
    }
}
