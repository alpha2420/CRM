@extends('layouts.app')
@section('title', 'Inbox')
@section('subtitle', 'WhatsApp conversations with your leads')

@section('content')
    <section @class(['card', 'flush', 'inbox', 'has-chat' => $lead])>
        <div class="inbox-list">
            <nav class="tabs">
                <a href="{{ route('inbox', array_filter(['lead' => $lead?->id])) }}" @class(['active' => ! $unreadOnly])>All</a>
                <a href="{{ route('inbox', array_filter(['unread' => 1, 'lead' => $lead?->id])) }}" @class(['active' => $unreadOnly])>Unread</a>
            </nav>
            <ul class="conversations">
                @forelse ($conversations as $conversation)
                    @php($last = $conversation->latestWhatsAppMessage)
                    <li>
                        <a href="{{ route('inbox', array_filter(['lead' => $conversation->id, 'unread' => $unreadOnly ? 1 : null])) }}" @class(['active' => $lead?->id === $conversation->id, 'unread' => $conversation->unread_count])>
                            <x-avatar :name="$conversation->name"/>
                            <span class="conv-main">
                                <span class="conv-top"><strong>{{ $conversation->name }}</strong><span>{{ $conversation->last_message_at->diffForHumans(short: true) }}</span></span>
                                <span class="conv-preview">@if ($last && ! $last->isInbound())You: @endif{{ $last?->body }}</span>
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
                    <x-avatar :name="$lead->name"/>
                    <div class="grow">
                        <strong>{{ $lead->name }}</strong>
                        <div class="muted small">{{ $lead->phone }} · {{ $lead->assignee?->name ?? 'Unassigned' }}</div>
                    </div>
                    @include('partials.status', ['status' => $lead->status])
                    <a href="{{ route('leads.show', $lead) }}" class="btn small">Open lead</a>
                </div>
                @include('leads._whatsapp', ['context' => 'inbox'])
            @else
                <div class="inbox-empty">
                    <x-empty icon="inbox" title="Select a conversation" text="Pick a chat on the left to read and reply."/>
                </div>
            @endif
        </div>
    </section>
@endsection
