<?php

namespace App\Observers;

use App\Models\Automation;
use App\Models\CustomField;
use App\Models\Integration;
use App\Models\LeadStatus;
use App\Models\RoutingRule;
use App\Models\Sequence;
use App\Models\Source;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Activity-log lines for team and settings changes, wherever they happen.
 */
class AuditTrail
{
    /** class => [area, label, attributes worth logging on update] */
    private const SUBJECTS = [
        LeadStatus::class => ['stage', 'pipeline stage', ['name', 'type', 'color', 'sort_order']],
        Source::class => ['source', 'lead source', ['name']],
        CustomField::class => ['field', 'custom field', ['label', 'type', 'options', 'is_required', 'sort_order']],
        Automation::class => ['automation', 'automation', ['name', 'trigger', 'conditions', 'actions', 'is_active']],
        Sequence::class => ['sequence', 'sequence', ['name', 'is_active', 'stop_on_reply']],
        RoutingRule::class => ['routing', 'routing rule', ['name', 'position', 'conditions', 'agent_ids', 'is_active']],
        Integration::class => ['integration', 'connection', ['settings', 'is_active']],
        User::class => ['user', 'team member', ['name', 'email', 'role', 'is_active']],
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created', 'Added');
    }

    public function updated(Model $model): void
    {
        [, , $attributes] = self::SUBJECTS[$model::class];

        if (array_intersect($attributes, array_keys($model->getChanges())) !== []) {
            $this->record($model, 'updated', 'Updated');
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', 'Removed');
    }

    private function record(Model $model, string $event, string $verb): void
    {
        [$area, $label] = self::SUBJECTS[$model::class];

        app(AuditLogger::class)->log("{$area}.{$event}", "{$verb} {$label} “{$this->name($model)}”", $model);
    }

    private function name(Model $model): string
    {
        return match (true) {
            $model instanceof Integration => $model->type->label(),
            $model instanceof CustomField => $model->label,
            default => (string) $model->getAttribute('name'),
        };
    }
}
