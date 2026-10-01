<?php

namespace App\Http\Controllers;

use App\Integrations\WhatsAppService;
use App\Models\Lead;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * WhatsApp conversations, newest first, with the selected chat open beside
 * the list. Agents see their own leads only.
 */
class InboxController extends Controller
{
    public function __invoke(Request $request, WhatsAppService $whatsapp): View
    {
        $user = $request->user();
        $unreadOnly = $request->boolean('unread');
        $unread = fn (Builder $q) => $q->where('direction', WhatsAppMessage::IN)->whereNull('read_at');

        // Open the selected chat first, so it is already read in the list.
        $lead = null;
        $composer = [];
        if ($request->filled('lead')) {
            $lead = Lead::query()->with(['status', 'assignee', 'organization'])->findOrFail((int) $request->query('lead'));
            Gate::authorize('view', $lead);
            $whatsapp->markRead($lead);
            $composer = $whatsapp->composerData($lead, (int) $request->query('template') ?: null);
        }

        $conversations = Lead::query()
            ->visibleTo($user)
            ->whereNotNull('last_message_at')
            ->withCount(['whatsappMessages as unread_count' => $unread])
            ->with(['latestWhatsAppMessage', 'status'])
            ->when($unreadOnly, fn (Builder $q) => $q->whereHas('whatsappMessages', $unread))
            ->orderByDesc('last_message_at')
            ->paginate(40)
            ->withQueryString();

        $data = ['conversations' => $conversations, 'unreadOnly' => $unreadOnly, 'lead' => $lead] + $composer;

        return view('inbox.index', $data);
    }
}
