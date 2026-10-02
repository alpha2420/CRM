<?php

namespace App\Tasks;

use App\Enums\AppointmentStatus;
use App\Enums\StatusType;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Task;
use App\Models\User;
use App\Speed\ResponseTimes;
use App\Support\LocalTime;
use Illuminate\Support\Collection;

/**
 * Everything one person has to do today, in one list: overdue work first,
 * then today's follow-ups, meetings and to-dos by time, new leads still
 * waiting for a reply, and to-dos without a date.
 */
final class MyDay
{
    public function __construct(private readonly ResponseTimes $responseTimes) {}

    /**
     * @return array{overdue: Collection<int, AgendaItem>, today: Collection<int, AgendaItem>, someday: Collection<int, AgendaItem>, waiting: Collection<int, array{lead: Lead, seconds: int}>, done: int}
     */
    public function for(User $user): array
    {
        $endOfToday = LocalTime::endOfToday();
        $waiting = $this->responseTimes->waiting($user, 10, onlyTheirs: true);
        $waitingIds = $waiting->pluck('lead.id');

        // New leads still waiting for a first reply have their own card.
        $followUps = Lead::query()
            ->where('assigned_to', $user->id)
            ->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Open)->select('id'))
            ->where('next_follow_up_at', '<=', $endOfToday)
            ->whereNotIn('id', $waitingIds)
            ->with('status')
            ->get()
            ->map(fn (Lead $lead) => AgendaItem::followUp($lead));

        $meetings = Appointment::query()
            ->where('user_id', $user->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->whereBetween('starts_at', [LocalTime::startOfToday(), $endOfToday])
            ->with('lead')
            ->get()
            ->map(fn (Appointment $meeting) => AgendaItem::meeting($meeting));

        $tasks = $user->tasks()->open()->inOrder()->with('lead')->get();
        $dated = $tasks->filter(fn (Task $task) => $task->due_at !== null && $task->due_at->lte($endOfToday))
            ->map(fn (Task $task) => AgendaItem::task($task));

        $all = $followUps->concat($meetings)->concat($dated)->sortBy(fn (AgendaItem $item) => $item->at?->getTimestamp() ?? PHP_INT_MAX)->values();

        return [
            'overdue' => $all->filter->isOverdue()->values(),
            'today' => $all->reject->isOverdue()->values(),
            'someday' => $tasks->whereNull('due_at')->map(fn (Task $task) => AgendaItem::task($task))->values(),
            'waiting' => $waiting,
            'done' => $this->doneToday($user),
        ];
    }

    /** To-dos ticked off and follow-ups logged today: shown as progress. */
    public function doneToday(User $user): int
    {
        $since = LocalTime::startOfToday();

        return $user->tasks()->where('done_at', '>=', $since)->count()
            + LeadActivity::query()->where('user_id', $user->id)->where('created_at', '>=', $since)->count();
    }

    /** For the sidebar badge: what is due by the end of today. */
    public function dueCount(User $user): int
    {
        $endOfToday = LocalTime::endOfToday();

        return Lead::query()
            ->where('assigned_to', $user->id)
            ->whereIn('status_id', LeadStatus::query()->where('type', StatusType::Open)->select('id'))
            ->where('next_follow_up_at', '<=', $endOfToday)
            ->count()
            + $user->tasks()->open()->where('due_at', '<=', $endOfToday)->count();
    }
}
