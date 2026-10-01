@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div>
            <a href="{{ route('settings.automations.index') }}" class="back"><x-icon name="arrow-left" class="icon sm"/>Automations</a>
            <h2>{{ $automation->exists ? 'Edit automation' : 'New automation' }}</h2>
            <p>Pick a trigger, optionally narrow it down, then choose what happens.</p>
        </div>
    </div>

    <form method="post" action="{{ $automation->exists ? route('settings.automations.update', $automation) : route('settings.automations.store') }}" class="card stack automation-form">
        @csrf
        @if ($automation->exists) @method('put') @endif

        <label>Name <input name="name" value="{{ old('name', $automation->name) }}" required maxlength="100" placeholder="e.g. Welcome new Facebook leads"></label>

        <fieldset>
            <legend>1 · When</legend>
            <select name="trigger" required>
                @foreach (\App\Enums\AutomationTrigger::cases() as $trigger)
                    <option value="{{ $trigger->value }}" @selected(old('trigger', $automation->trigger?->value) === $trigger->value)>{{ $trigger->label() }}</option>
                @endforeach
            </select>
        </fieldset>

        <fieldset>
            <legend>2 · Only if <span class="muted">(optional)</span></legend>
            <div class="form-grid">
                <label>Source is
                    <select name="conditions[source_id]">
                        <option value="">Any source</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected(old('conditions.source_id', $automation->condition('source_id')) == $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Status is
                    <select name="conditions[status_id]">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected(old('conditions.status_id', $automation->condition('status_id')) == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>3 · Then</legend>
            <div class="form-grid">
                <label>Assign to
                    <select name="actions[assign_to]">
                        <option value="">Don't change</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('actions.assign_to', $automation->action('assign_to')) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Set status
                    <select name="actions[set_status_id]">
                        <option value="">Don't change</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected(old('actions.set_status_id', $automation->action('set_status_id')) == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Send WhatsApp template
                    <select name="actions[whatsapp_template_id]">
                        <option value="">None</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('actions.whatsapp_template_id', $automation->action('whatsapp_template_id')) == $template->id)>{{ $template->label() }}</option>
                        @endforeach
                    </select>
                    @if ($templates->isEmpty())<span class="muted small">Connect WhatsApp and sync templates to use this.</span>@endif
                </label>
                <label>Schedule follow-up in (hours)
                    <input type="number" name="actions[follow_up_in_hours]" value="{{ old('actions.follow_up_in_hours', $automation->action('follow_up_in_hours')) }}" min="1" max="720" placeholder="e.g. 2">
                </label>
                <label>Notify
                    <select name="actions[notify_user_id]">
                        <option value="">Nobody extra</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('actions.notify_user_id', $automation->action('notify_user_id')) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn primary">{{ $automation->exists ? 'Save changes' : 'Turn on automation' }}</button>
            <a href="{{ route('settings.automations.index') }}" class="btn ghost">Cancel</a>
        </div>
    </form>

    @if ($automation->exists)
        <form method="post" action="{{ route('settings.automations.destroy', $automation) }}" data-confirm="Delete this automation?">
            @csrf @method('delete')
            <button type="submit" class="btn danger small"><x-icon name="trash"/>Delete automation</button>
        </form>
    @endif
@endsection
