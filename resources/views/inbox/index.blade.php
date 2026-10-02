@extends('layouts.app')
@section('guide', 'inbox')
@section('title', 'Inbox')
@section('subtitle', 'WhatsApp conversations with your leads')

@section('content')
    <section @class(['card', 'flush', 'inbox', 'has-chat' => $lead, 'no-panel' => ! $lead])>
        <div class="inbox-list">
            <div class="inbox-list-head">
                <h2>Conversations</h2>
                <div class="segmented" role="group" aria-label="Show">
                    <a href="{{ route('inbox', array_filter(['lead' => $lead?->id])) }}" @class(['active' => ! $unreadOnly])>All</a>
                    <a href="{{ route('inbox', array_filter(['unread' => 1, 'lead' => $lead?->id])) }}" @class(['active' => $unreadOnly])>Unread</a>
                </div>
            </div>
            <ul class="conversations">
                @forelse ($conversations as $conversation)
                    @php($last = $conversation->latestWhatsAppMessage)
                    <li>
                        <a href="{{ route('inbox', array_filter(['lead' => $conversation->id, 'unread' => $unreadOnly ? 1 : null])) }}" @class(['active' => $lead?->id === $conversation->id, 'unread' => $conversation->unread_count])>
                            <x-avatar :name="$conversation->name" size="md"/>
                            <span class="conv-main">
                                <span class="conv-top"><strong>{{ $conversation->name }}</strong><span>{{ $conversation->last_message_at->diffForHumans(short: true) }}</span></span>
                                <span class="conv-preview">@if ($last && ! $last->isInbound())You: @endif{{ $last?->preview() }}</span>
                            </span>
                            @if ($conversation->unread_count)<span class="count">{{ $conversation->unread_count }}</span>@endif
                        </a>
                    </li>
                @empty
                    <li><x-empty icon="inbox" :title="$unreadOnly ? 'No unread messages' : 'No conversations yet'" text="When leads message your WhatsApp number, their chats appear here."/></li>
                @endforelse
            </ul>
        </div>

        <div class="inbox-chat">
            @if ($lead)
                <div class="chat-head">
                    <a href="{{ route('inbox') }}" class="icon-btn hide-desktop" aria-label="Back to conversations"><x-icon name="arrow-left"/></a>
                    <x-avatar :name="$lead->name" size="md"/>
                    <div class="grow">
                        <strong>{{ $lead->name }}</strong>
                        <div class="muted small">{{ $lead->phone }}{{ $lead->company ? ' · '.$lead->company : '' }}</div>
                    </div>
                    @if ($lead->whatsappWindowOpen())<span class="pill ok hide-sm">Reply window open</span>@else<span class="pill hide-sm">Templates only</span>@endif
                    <a href="tel:{{ $lead->phone }}" class="tool" aria-label="Call {{ $lead->name }}"><x-icon name="phone"/></a>
                    <a href="{{ route('leads.show', $lead) }}" class="btn small hide-desktop">Open lead</a>
                </div>
                @include('leads._whatsapp', ['context' => 'inbox'])
            @else
                <div class="inbox-empty">
                    <x-empty icon="inbox" title="Select a conversation" text="Pick a chat on the left to read and reply."/>
                </div>
            @endif
        </div>

        @if ($lead)
            <aside class="inbox-panel" aria-label="Lead details">
                <div class="who">
                    <x-avatar :name="$lead->name" size="lg"/>
                    <strong>{{ $lead->name }}</strong>
                    <span class="muted small">{{ collect([$lead->company, $lead->city])->filter()->join(' · ') ?: $lead->phone }}</span>
                </div>
                @can('update', $lead)
                    <form method="post" action="{{ route('leads.move', $lead) }}">
                        @csrf @method('patch')
                        <label>Stage
                            <select name="status_id" data-autosubmit>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}" @selected($status->id === $lead->status_id)>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <noscript><button type="submit" class="btn small">Change stage</button></noscript>
                    </form>
                @else
                    <div>@include('partials.status', ['status' => $lead->status])</div>
                @endcan
                @php($due = \App\Support\FollowUp::describe($lead->next_follow_up_at))
                <dl class="kv-rows">
                    <div><dt>Next follow-up</dt><dd><span class="due {{ $due['tone'] }}">{{ $due['text'] }}</span></dd></div>
                    <div><dt>Owner</dt><dd>{{ $lead->assignee?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Source</dt><dd>{{ $lead->source?->name ?? '—' }}</dd></div>
                    <div><dt>Deal value</dt><dd>{{ $lead->value !== null ? \App\Support\Money::full($lead->value) : '—' }}</dd></div>
                    <div><dt>Added</dt><dd>{{ $lead->created_at->local()->format('j M Y') }}</dd></div>
                </dl>
                <a href="{{ route('leads.show', $lead) }}" class="btn block">Open lead<x-icon name="chevron-right"/></a>
            </aside>
        @endif
    </section>
@endsection
