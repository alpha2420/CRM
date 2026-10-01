<?php

namespace App\Services;

use App\Enums\LeadStage;
use App\Enums\Role;
use App\Enums\StatusType;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Source;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;

final class DashboardStats
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $leads = fn (): Builder => Lead::query()->visibleTo($user);
        $wonStatusIds = LeadStatus::query()->where('type', StatusType::Won)->select('id');
        $openStatusIds = LeadStatus::query()->where('type', StatusType::Open)->select('id');
        $weekStart = LocalTime::startOfToday()->subDays(6);
        $unread = fn (Builder $q) => $q->where('direction', WhatsAppMessage::IN)->whereNull('read_at');

        $total = $leads()->count();
        $won = $leads()->whereIn('status_id', $wonStatusIds)->count();
        $wonThisMonth = $leads()->whereIn('status_id', $wonStatusIds)->where('closed_at', '>=', LocalTime::startOfMonth());

        return [
            'total' => $total,
            'new_today' => $leads()->where('created_at', '>=', LocalTime::startOfToday())->count(),
            'new_week' => $leads()->where('created_at', '>=', $weekStart)->count(),
            'new_previous_week' => $leads()->whereBetween('created_at', [$weekStart->copy()->subDays(7), $weekStart])->count(),
            'due' => $leads()->inStage(LeadStage::Due)->count(),
            'overdue' => $leads()->inStage(LeadStage::Due)->where('next_follow_up_at', '<', now())->count(),
            'open' => $leads()->whereIn('status_id', $openStatusIds)->count(),
            'open_value' => (float) $leads()->whereIn('status_id', $openStatusIds)->sum('value'),
            'dormant' => $leads()->inStage(LeadStage::Dormant)->count(),
            'won' => $won,
            'won_this_month' => (clone $wonThisMonth)->count(),
            'won_value_this_month' => (float) $wonThisMonth->sum('value'),
            'conversion' => $total > 0 ? round($won / $total * 100, 1) : 0.0,

            'by_status' => LeadStatus::query()->ordered()
                ->where('type', '!=', StatusType::Lost)
                ->withCount(['leads' => fn (Builder $q) => $q->visibleTo($user)])
                ->withSum(['leads' => fn (Builder $q) => $q->visibleTo($user)], 'value')
                ->get(),

            'by_source' => Source::query()
                ->withCount(['leads' => fn (Builder $q) => $q->visibleTo($user)->where('created_at', '>=', now()->subDays(30))])
                ->orderByDesc('leads_count')
                ->get()
                ->where('leads_count', '>', 0)
                ->values(),

            'by_agent' => $user->isAdmin()
                ? $user->organization->users()
                    ->where('role', Role::Agent)
                    ->withCount([
                        'assignedLeads as open_count' => fn (Builder $q) => $q->whereIn('status_id', $openStatusIds),
                        'assignedLeads as won_count' => fn (Builder $q) => $q->whereIn('status_id', $wonStatusIds),
                    ])
                    ->orderByDesc('won_count')
                    ->orderBy('name')
                    ->get()
                : collect(),

            'upcoming' => $leads()->inStage(LeadStage::Due)
                ->with(['status', 'assignee'])
                ->orderBy('next_follow_up_at')
                ->limit(6)
                ->get(),

            'chats' => $leads()
                ->whereHas('whatsappMessages', $unread)
                ->withCount(['whatsappMessages as unread_count' => $unread])
                ->with('latestWhatsAppMessage')
                ->orderByDesc('last_message_at')
                ->limit(4)
                ->get(),
        ];
    }
}
