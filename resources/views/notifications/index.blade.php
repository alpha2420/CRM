@extends('layouts.app')
@section('title', 'Notifications')
@section('subtitle', 'New leads, follow-ups due, messages and automation alerts.')
@section('actions')
    @if (auth()->user()->unreadNotifications()->exists())
        <form method="post" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn"><x-icon name="check"/>Mark all read</button>
        </form>
    @endif
@endsection

@section('content')
    @if (app(\App\Push\PushSender::class)->isConfigured())
        <section class="card" id="push-card" style="max-width: 820px"
                 data-key="{{ config('services.webpush.public_key') }}"
                 data-store-url="{{ route('push.store') }}" data-destroy-url="{{ route('push.destroy') }}" data-test-url="{{ route('push.test') }}">
            <div class="row-between" style="flex-wrap:wrap">
                <div class="row"><span class="kpi-icon"><x-icon name="smartphone"/></span><div><strong>Notifications on this device</strong><div class="muted small" data-push-status>Checking…</div></div></div>
                <div class="row">
                    <button type="button" class="btn small" data-push-test hidden>Send a test</button>
                    <button type="button" class="btn primary small" data-push-toggle>Turn on for this device</button>
                </div>
            </div>
        </section>
        @push('scripts')<script src="{{ asset('js/push.js') }}" defer></script>@endpush
    @endif
    @php($icons = ['lead' => 'user-plus', 'reminder' => 'clock', 'whatsapp' => 'whatsapp', 'automation' => 'zap'])
    <section class="card flush" style="max-width: 820px">
        @if ($notifications->isEmpty())
            <x-empty icon="bell" title="You're all caught up" text="We'll let you know when a lead is assigned to you, a follow-up is due or a lead messages you."/>
        @else
            <ul class="notifications">
                @php($group = null)
                @foreach ($notifications as $notification)
                    @php($label = $notification->created_at->local()->isToday() ? 'Today' : 'Earlier')
                    @if ($label !== $group)
                        <li class="list-group-label">{{ $label }}</li>
                        @php($group = $label)
                    @endif
                    @php($kind = $notification->data['kind'] ?? 'lead')
                    <li>
                        <a href="{{ route('notifications.open', $notification->id) }}" @class(['unread' => ! $notification->read_at])>
                            <span class="n-icon {{ $kind }}"><x-icon :name="$icons[$kind] ?? 'bell'"/></span>
                            <span class="grow">
                                <strong>{{ $notification->data['title'] ?? 'Notification' }}</strong>
                                <span class="muted small" style="display:block">{{ $notification->data['body'] ?? '' }}</span>
                            </span>
                            <span class="faint small nowrap">{{ $notification->created_at->diffForHumans(short: true) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    {{ $notifications->links() }}
@endsection
