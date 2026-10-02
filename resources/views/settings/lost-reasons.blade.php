@extends('layouts.settings')
@section('guide', 'lost_reasons')

@section('settings')
    <div class="settings-head">
        <div><h2>Lost reasons</h2><p>Why leads are lost, asked when someone marks a lead lost. Reports show which reasons cost you most. With win-back on (Settings → Autopilot), a lead is reopened after the days you set.</p></div>
    </div>
    <section class="card flush">
        <div class="list-row reason-row head"><span>Reason</span><span>Win back after</span><span class="num">Leads</span><span></span><span></span></div>
        @foreach ($reasons as $reason)
            <div class="list-row reason-row">
                <form method="post" action="{{ route('settings.lost-reasons.update', $reason) }}" class="contents">
                    @csrf @method('put')
                    <input name="name" value="{{ $reason->name }}" required maxlength="60" aria-label="Reason">
                    <span class="inline-form"><input type="number" name="win_back_after_days" value="{{ $reason->win_back_after_days }}" min="7" max="365" placeholder="never" class="inline-num" aria-label="Win back after (days)"><span class="muted small">days</span></span>
                    <span class="num muted">{{ number_format($reason->leads_count) }}</span>
                    <button type="submit" class="btn small">Save</button>
                </form>
                <form method="post" action="{{ route('settings.lost-reasons.destroy', $reason) }}" data-confirm="Delete this reason? Leads that had it keep no reason.">
                    @csrf @method('delete')
                    <button type="submit" class="icon-btn" aria-label="Delete {{ $reason->name }}"><x-icon name="trash"/></button>
                </form>
            </div>
        @endforeach
        <form method="post" action="{{ route('settings.lost-reasons.store') }}" class="list-row reason-row add">
            @csrf
            <input name="name" placeholder="New reason, e.g. Budget not approved" required maxlength="60" aria-label="New reason">
            <span class="inline-form"><input type="number" name="win_back_after_days" min="7" max="365" placeholder="never" class="inline-num" aria-label="Win back after (days)"><span class="muted small">days</span></span>
            <span></span>
            <button type="submit" class="btn small primary">Add</button>
        </form>
    </section>
    <p class="hint">Leave "win back after" empty for reasons that aren't worth another try, like choosing a competitor.</p>
@endsection
