@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div><h2>Pipeline</h2><p>The stages a lead moves through. <strong>Open</strong> stages are the pipeline; <strong>Won</strong> and <strong>Lost</strong> close the lead and drive your reports.</p></div>
    </div>
    <section class="card flush">
        <div class="list-row status-row head"><span>Stage</span><span>Type</span><span>Colour</span><span>Order</span><span class="num">Leads</span><span></span><span></span></div>
        @foreach ($statuses as $status)
            <div class="list-row status-row">
                <form method="post" action="{{ route('settings.statuses.update', $status) }}" class="contents">
                    @csrf @method('put')
                    <input name="name" value="{{ $status->name }}" required maxlength="50" aria-label="Stage name">
                    <select name="type" aria-label="Type">
                        @foreach (\App\Enums\StatusType::cases() as $type)
                            <option value="{{ $type->value }}" @selected($status->type === $type)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <input type="color" name="color" value="{{ $status->color }}" aria-label="Colour">
                    <input type="number" name="sort_order" value="{{ $status->sort_order }}" min="0" max="999" required aria-label="Order">
                    <span class="num muted">{{ $status->leads_count }}</span>
                    <button type="submit" class="btn small">Save</button>
                </form>
                <form method="post" action="{{ route('settings.statuses.destroy', $status) }}" data-confirm="Delete the “{{ $status->name }}” stage?">
                    @csrf @method('delete')
                    <button type="submit" class="icon-btn" aria-label="Delete {{ $status->name }}"><x-icon name="trash"/></button>
                </form>
            </div>
        @endforeach
        <form method="post" action="{{ route('settings.statuses.store') }}" class="list-row status-row add">
            @csrf
            <input name="name" placeholder="New stage, e.g. Proposal sent" required maxlength="50" aria-label="New stage name">
            <select name="type" aria-label="Type">
                @foreach (\App\Enums\StatusType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
            <input type="color" name="color" value="#64748b" aria-label="Colour">
            <input type="number" name="sort_order" value="{{ ($statuses->max('sort_order') ?? 0) + 1 }}" min="0" max="999" required aria-label="Order">
            <span></span>
            <button type="submit" class="btn small primary">Add</button>
        </form>
    </section>
    <p class="muted small">New leads start in the first open stage. A stage with leads in it can't be deleted.</p>
@endsection
