<?php

namespace App\Autopilot;

use App\Enums\LeadStage;
use App\Enums\Role;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use App\Notifications\WeeklyReportNotification;
use App\Services\ReportService;
use App\Support\FollowUp;
use App\Support\LocalTime;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Morning summaries at 9:00 in each workspace's own time zone: everyone
 * gets their day's follow-ups, admins also see who is behind, and on
 * Mondays admins get last week's numbers.
 */
final class Digests
{
    public const HOUR = 9;

    public function __construct(
        private readonly ReportService $reports,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return array{daily: int, weekly: int}
     */
    public function sendDue(): array
    {
        $sent = ['daily' => 0, 'weekly' => 0];

        foreach (Organization::query()->whereNull('suspended_at')->cursor() as $organization) {
            $local = LocalTime::of(now(), $organization->timezone);
            $settings = $organization->autopilot();

            if ($local->hour !== self::HOUR || ! $organization->isActive()) {
                continue;
            }

            $this->tenant->set($organization->id);
            $users = $organization->users()->active()->get()->each->setRelation('organization', $organization);

            // Cache::add makes each summary go out once, even if the
            // scheduler runs twice in the hour.
            if ($settings->on('daily_digest') && Cache::add("digest:daily:{$organization->id}:{$local->toDateString()}", true, now()->addDay())) {
                foreach ($users as $user) {
                    if ($digest = $this->dailyFor($user)) {
                        $user->notify(new DailyDigestNotification($digest));
                        $sent['daily']++;
                    }
                }
            }

            if ($local->isMonday() && $settings->on('weekly_report') && Cache::add("digest:weekly:{$organization->id}:{$local->toDateString()}", true, now()->addDay())) {
                $from = LocalTime::now()->subDays(7)->startOfDay();
                $to = LocalTime::now()->subDay()->endOfDay();

                foreach ($users->where('role', Role::Admin) as $admin) {
                    $admin->notify(new WeeklyReportNotification($this->reports->build($admin, $from, $to)['kpis'], $from, $to));
                    $sent['weekly']++;
                }
            }
        }

        $this->tenant->clear();

        return $sent;
    }

    /**
     * What one person should know this morning, or null if there is
     * nothing worth an email.
     *
     * @return array{name: string, due: int, overdue: int, tasks: int, new: int, leads: list<array{name: string, when: string}>, team: array<string, int>}|null
     */
    public function dailyFor(User $user): ?array
    {
        $due = Lead::query()->visibleTo($user)->inStage(LeadStage::Due);
        $yesterday = LocalTime::startOfToday()->subDay();

        $digest = [
            'name' => $user->name,
            'due' => (clone $due)->count(),
            'overdue' => (clone $due)->where('next_follow_up_at', '<', LocalTime::startOfToday())->count(),
            'tasks' => $user->tasks()->open()->where('due_at', '<=', LocalTime::endOfToday())->count(),
            'new' => Lead::query()->visibleTo($user)->whereBetween('created_at', [$yesterday, LocalTime::startOfToday()])->count(),
            'leads' => (clone $due)->orderBy('next_follow_up_at')->limit(5)->get()
                ->map(fn (Lead $lead) => ['name' => $lead->name, 'when' => FollowUp::describe($lead->next_follow_up_at)['text']])
                ->all(),
            'team' => [],
        ];

        if ($user->isAdmin()) {
            $digest['team'] = $user->organization->users()->active()->where('role', Role::Agent)->get()
                ->mapWithKeys(fn (User $agent) => [$agent->name => Lead::query()->visibleTo($agent)->inStage(LeadStage::Due)
                    ->where('next_follow_up_at', '<', LocalTime::startOfToday())->count()])
                ->filter()
                ->all();
        }

        $nothing = $digest['due'] === 0 && $digest['tasks'] === 0 && $digest['new'] === 0 && $digest['team'] === [];

        return $nothing ? null : $digest;
    }
}
