@extends('layouts.settings')

@section('settings')
    @php($conditions = old('conditions', $rule->conditions ?? []))
    @php($chosen = array_map('intval', old('agent_ids', $rule->agent_ids ?? [])))
    <div class="settings-head">
        <div>
            <a href="{{ route('settings.routing.index') }}" class="back"><x-icon name="arrow-left" class="icon sm"/>Lead routing</a>
            <h2>{{ $rule->exists ? 'Edit routing rule' : 'New routing rule' }}</h2>
            <p>Leads that match every condition you set go to the people you pick, taking turns.</p>
        </div>
    </div>

    <form method="post" action="{{ $rule->exists ? route('settings.routing.update', $rule) : route('settings.routing.store') }}" class="card stack">
        @csrf
        @if ($rule->exists) @method('put') @endif

        <label>Name <input name="name" value="{{ old('name', $rule->name) }}" required maxlength="100" placeholder="e.g. Pune Facebook leads"></label>

        <fieldset>
            <legend>1 · Leads that match</legend>
            <div class="form-grid two">
                <label>Source is
                    <select name="conditions[source_id]">
                        <option value="">Any source</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected(($conditions['source_id'] ?? '') == $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>City is <input name="conditions[city]" value="{{ $conditions['city'] ?? '' }}" maxlength="100" placeholder="Any city"></label>
                @if ($fields->isNotEmpty())
                    <label>And field
                        <select name="conditions[field_key]">
                            <option value="">No field</option>
                            @foreach ($fields as $field)
                                <option value="{{ $field->key }}" @selected(($conditions['field_key'] ?? '') === $field->key)>{{ $field->label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>is <input name="conditions[field_value]" value="{{ $conditions['field_value'] ?? '' }}" maxlength="150" placeholder="e.g. 2BHK"></label>
                @endif
            </div>
            <span class="hint">City and field values are compared without caring about capital letters.</span>
        </fieldset>

        <fieldset>
            <legend>2 · Go to</legend>
            <div class="check-grid">
                @foreach ($users as $user)
                    <label class="check"><input type="checkbox" name="agent_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, $chosen, true))>{{ $user->name }} <span class="muted small">{{ $user->role->label() }}</span></label>
                @endforeach
            </div>
            <span class="hint">With more than one person they take turns. People who are away or at the lead limit are skipped.</span>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn primary">{{ $rule->exists ? 'Save changes' : 'Save rule' }}</button>
            <a href="{{ route('settings.routing.index') }}" class="btn ghost">Cancel</a>
        </div>
    </form>

    @if ($rule->exists)
        <form method="post" action="{{ route('settings.routing.destroy', $rule) }}" data-confirm="Delete this routing rule?">
            @csrf @method('delete')
            <button type="submit" class="btn danger small"><x-icon name="trash"/>Delete rule</button>
        </form>
    @endif
@endsection
