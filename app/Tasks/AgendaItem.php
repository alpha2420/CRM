<?php

namespace App\Tasks;

use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Task;
use Carbon\CarbonInterface;

/**
 * One line in "My day": a follow-up to make, a meeting to attend or a
 * to-do to tick off.
 */
final readonly class AgendaItem
{
    public const FOLLOW_UP = 'follow_up';

    public const MEETING = 'meeting';

    public const TASK = 'task';

    private function __construct(
        public string $kind,
        public string $title,
        public ?CarbonInterface $at,
        public ?Lead $lead = null,
        public ?Task $task = null,
        public ?Appointment $meeting = null,
    ) {}

    public static function followUp(Lead $lead): self
    {
        return new self(self::FOLLOW_UP, "Follow up with {$lead->name}", $lead->next_follow_up_at, $lead);
    }

    public static function meeting(Appointment $meeting): self
    {
        return new self(self::MEETING, "{$meeting->type->label()} with {$meeting->lead->name}", $meeting->starts_at, $meeting->lead, meeting: $meeting);
    }

    public static function task(Task $task): self
    {
        return new self(self::TASK, $task->title, $task->due_at, $task->lead, $task);
    }

    public function isOverdue(): bool
    {
        return $this->kind !== self::MEETING && $this->at?->isPast() === true;
    }

    public function icon(): string
    {
        return match ($this->kind) {
            self::FOLLOW_UP => 'phone',
            self::MEETING => 'calendar',
            default => 'check-circle',
        };
    }
}
