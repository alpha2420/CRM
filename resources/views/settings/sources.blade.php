@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div><h2>Lead sources</h2><p>Where your leads come from. Used in filters, automations and the source report.</p></div>
    </div>
    <section class="card flush">
        <div class="list-row source-row head"><span>Source</span><span class="num">Leads</span><span></span><span></span></div>
        @foreach ($sources as $source)
            <div class="list-row source-row">
                <form method="post" action="{{ route('settings.sources.update', $source) }}" class="contents">
                    @csrf @method('put')
                    <input name="name" value="{{ $source->name }}" required maxlength="50" aria-label="Source name">
                    <span class="num muted">{{ number_format($source->leads_count) }}</span>
                    <button type="submit" class="btn small">Save</button>
                </form>
                <form method="post" action="{{ route('settings.sources.destroy', $source) }}" data-confirm="Delete this source? Its leads stay, without a source.">
                    @csrf @method('delete')
                    <button type="submit" class="icon-btn" aria-label="Delete {{ $source->name }}"><x-icon name="trash"/></button>
                </form>
            </div>
        @endforeach
        <form method="post" action="{{ route('settings.sources.store') }}" class="list-row source-row add">
            @csrf
            <input name="name" placeholder="New source, e.g. Google Ads" required maxlength="50" aria-label="New source name">
            <span></span>
            <button type="submit" class="btn small primary">Add</button>
        </form>
    </section>
@endsection
