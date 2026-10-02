@extends('layouts.app')
@section('title', 'Broadcasts')
@section('guide', 'broadcasts')
@section('subtitle', 'Send one approved WhatsApp template to many leads at once.')
@section('actions')
    <a href="{{ route('broadcasts.create') }}" class="btn primary"><x-icon name="plus"/>New broadcast</a>
@endsection

@section('content')
    <section class="card flush">
        @if ($broadcasts->isEmpty())
            <x-empty icon="megaphone" title="No broadcasts yet" text="Send an offer, an event invite or a reminder to a group of leads, for example all open leads from Facebook. People who said STOP are always left out.">
                <a href="{{ route('broadcasts.create') }}" class="btn primary"><x-icon name="plus"/>New broadcast</a>
            </x-empty>
        @else
            <div class="scroll-x"><table>
                <thead><tr><th>Broadcast</th><th>Sent to</th><th class="num">Sent</th><th class="num">Read</th><th class="num">Replied</th><th>When</th></tr></thead>
                <tbody>
                @foreach ($broadcasts as $broadcast)
                    @php($results = $broadcast->results())
                    <tr>
                        <td><a href="{{ route('broadcasts.show', $broadcast) }}"><strong>{{ $broadcast->name }}</strong></a><div class="muted small">{{ $broadcast->template_name }}</div></td>
                        <td class="muted small">{{ app(\App\Broadcasts\BroadcastAudience::class)->describe($broadcast->audience) }}</td>
                        <td class="num">@if ($broadcast->status === \App\Models\Broadcast::SENDING)<span class="pill info">Sending…</span>@elseif ($broadcast->status === \App\Models\Broadcast::FAILED)<span class="pill bad">Not sent</span>@else{{ number_format($results['sent']) }}@endif</td>
                        <td class="num">{{ number_format($results['read']) }}</td>
                        <td class="num">{{ number_format($results['replied']) }}</td>
                        <td class="muted small">{{ $broadcast->created_at->local()->format('j M, H:i') }} · {{ $broadcast->user?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            {{ $broadcasts->links() }}
        @endif
    </section>
@endsection
