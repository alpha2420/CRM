@extends('layouts.app')
@section('title', $broadcast->name)
@section('subtitle', $broadcast->template_name.' · '.app(\App\Broadcasts\BroadcastAudience::class)->describe($broadcast->audience).' · sent '.$broadcast->created_at->local()->format('j M Y, H:i').' by '.($broadcast->user?->name ?? 'a former member'))

@section('content')
    <a href="{{ route('broadcasts.index') }}" class="back"><x-icon name="arrow-left"/>Broadcasts</a>
    @if ($broadcast->status === \App\Models\Broadcast::SENDING)
        <div class="alert info">Sending… Refresh in a minute to see the results.</div>
    @elseif ($broadcast->status === \App\Models\Broadcast::FAILED)
        <div class="alert error">Not sent: its template is no longer approved or was removed.</div>
    @endif

    @php($base = max(1, $results['sent']))
    <section class="kpis six">
        <div class="kpi"><span class="kpi-label">Sent</span><strong>{{ number_format($results['sent']) }}</strong><span class="sub">{{ $results['queued'] ? $results['queued'].' still queued' : 'accepted by WhatsApp' }}</span></div>
        <div class="kpi"><span class="kpi-label">Delivered</span><strong>{{ number_format($results['delivered']) }}</strong><span class="sub">{{ round($results['delivered'] / $base * 100) }}% of sent</span></div>
        <div class="kpi"><span class="kpi-label">Read</span><strong>{{ number_format($results['read']) }}</strong><span class="sub">{{ round($results['read'] / $base * 100) }}% of sent</span></div>
        <div class="kpi"><span class="kpi-label">Replied</span><strong class="tone-ok">{{ number_format($results['replied']) }}</strong><span class="sub">wrote back afterwards</span></div>
        <div class="kpi"><span class="kpi-label">Failed</span><strong @class(['tone-hot' => $results['failed']])>{{ number_format($results['failed']) }}</strong><span class="sub">WhatsApp refused</span></div>
        <div class="kpi"><span class="kpi-label">Skipped</span><strong>{{ number_format($broadcast->skipped) }}</strong><span class="sub">said STOP meanwhile</span></div>
    </section>

    <section class="card flush">
        <div class="card-head"><h2>Recipients</h2><span class="muted small">Open a lead to continue the conversation</span></div>
        <div class="scroll-x"><table>
            <thead><tr><th>Lead</th><th>Status</th><th>Note</th></tr></thead>
            <tbody>
            @forelse ($recipients as $message)
                <tr>
                    <td>@if ($message->lead)<a href="{{ route('leads.show', ['lead' => $message->lead, 'tab' => 'whatsapp']) }}">{{ $message->lead->name }}</a>@else — @endif</td>
                    <td><span @class(['pill', 'ok' => in_array($message->status, ['delivered', 'read'], true), 'bad' => $message->status === 'failed', 'info' => $message->status === 'queued'])>{{ ucfirst($message->status) }}</span></td>
                    <td class="muted small">{{ $message->error }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">No messages yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $recipients->links() }}
    </section>
@endsection
