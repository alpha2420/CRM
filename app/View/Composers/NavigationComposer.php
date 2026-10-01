<?php

namespace App\View\Composers;

use App\Enums\Feature;
use App\Enums\IntegrationType;
use App\Enums\LeadStage;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Badge counts for the sidebar (and whether WhatsApp is connected), computed
 * once per request and shared with every view that asks for them.
 */
class NavigationComposer
{
    public function compose(View $view): void
    {
        $request = request();

        if (! $request->attributes->has('nav')) {
            $request->attributes->set('nav', $this->counts());
        }

        $view->with('nav', $request->attributes->get('nav'));
    }

    /**
     * @return array{inbox: bool, unreadChats: int, unreadNotifications: int, dueFollowUps: int}
     */
    private function counts(): array
    {
        $user = Auth::user();
        $showInbox = $user->organization->canUse(Feature::WhatsApp)
            && Integration::query()->where('type', IntegrationType::WhatsApp)->exists();

        return [
            'inbox' => $showInbox,
            'unreadChats' => $showInbox
                ? WhatsAppMessage::query()
                    ->where('direction', WhatsAppMessage::IN)
                    ->whereNull('read_at')
                    ->whereHas('lead', fn (Builder $q) => $q->visibleTo($user))
                    ->count()
                : 0,
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'dueFollowUps' => Lead::query()->visibleTo($user)->inStage(LeadStage::Due)->count(),
        ];
    }
}
