<?php

namespace App\View\Composers;

use App\Enums\Feature;
use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Badge counts for the sidebar, computed once per page.
 */
class NavigationComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        $showInbox = $user->organization->canUse(Feature::WhatsApp)
            && Integration::query()->where('type', IntegrationType::WhatsApp)->exists();

        $view->with('nav', [
            'inbox' => $showInbox,
            'unreadChats' => $showInbox
                ? WhatsAppMessage::query()
                    ->where('direction', WhatsAppMessage::IN)
                    ->whereNull('read_at')
                    ->whereHas('lead', fn (Builder $q) => $q->visibleTo($user))
                    ->count()
                : 0,
            'unreadNotifications' => $user->unreadNotifications()->count(),
        ]);
    }
}
