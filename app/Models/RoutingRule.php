<?php

namespace App\Models;

use App\Observers\AuditTrail;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * "Leads that match these conditions go to these people, in turn."
 *
 * conditions: {source_id?: int, city?: string, field_key?: string, field_value?: string}
 * agent_ids:  list of user ids sharing the matching leads
 */
#[Fillable(['name', 'position', 'conditions', 'agent_ids', 'is_active'])]
#[ObservedBy(AuditTrail::class)]
class RoutingRule extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'conditions' => 'array',
            'agent_ids' => 'array',
            'is_active' => 'boolean',
            'last_assigned_user_id' => 'integer',
        ];
    }

    /**
     * Every condition that is set must hold (text compared case-insensitively).
     */
    public function matches(Lead $lead): bool
    {
        $sourceId = $this->conditions['source_id'] ?? null;
        $city = trim((string) ($this->conditions['city'] ?? ''));
        $fieldKey = (string) ($this->conditions['field_key'] ?? '');

        return ($sourceId === null || (int) $sourceId === (int) $lead->source_id)
            && ($city === '' || strcasecmp($city, trim((string) $lead->city)) === 0)
            && ($fieldKey === '' || strcasecmp(
                trim((string) ($this->conditions['field_value'] ?? '')),
                trim((string) ($lead->custom_values[$fieldKey] ?? '')),
            ) === 0);
    }

    /**
     * @return list<int>
     */
    public function agentIds(): array
    {
        return array_map('intval', $this->agent_ids ?? []);
    }
}
