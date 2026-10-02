@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div>
            <a href="{{ route('settings.automations.index') }}" class="back"><x-icon name="arrow-left" class="icon sm"/>Automations</a>
            <h2>{{ $automation->exists ? 'Edit automation' : 'New automation' }}</h2>
            <p>Pick a trigger, optionally narrow it down, then choose what happens. Time-based rules run inside working hours, once per occasion.</p>
        </div>
    </div>

    <form method="post" action="{{ $automation->exists ? route('settings.automations.update', $automation) : route('settings.automations.store') }}" class="card stack automation-form">
        @csrf
        @if ($automation->exists) @method('put') @endif

        <label>Name <input name="name" value="{{ old('name', $automation->name) }}" required maxlength="100" placeholder="e.g. Welcome new Facebook leads"></label>

        <fieldset>
            <legend>1 · When</legend>
            <div class="form-grid two">
                <label>Trigger
                    <select name="trigger" required data-trigger>
                        @foreach (\App\Enums\AutomationTrigger::cases() as $trigger)
                            <option value="{{ $trigger->value }}" data-unit="{{ $trigger->afterUnit() }}" @selected(old('trigger', $automation->trigger?->value) === $trigger->value)>{{ $trigger->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label data-when="scheduled">For at least <span class="inline-form"><input type="number" name="trigger_after" value="{{ old('trigger_after', $automation->trigger_after) }}" min="1" max="365" class="inline-num" placeholder="7"> <span data-unit-label class="muted">days</span></span></label>
                <label data-when="whatsapp_received">Only if the message mentions <input name="conditions[keywords]" value="{{ old('conditions.keywords', $automation->condition('keywords')) }}" maxlength="200" placeholder="e.g. price, cost, rate"><span class="hint">Separate words with commas. Leave empty for any message. Runs at most once a day per lead.</span></label>
            </div>
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
                <label>Priority is
                    <select name="conditions[priority]">
                        <option value="">Any priority</option>
                        @foreach (\App\Enums\Priority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected(old('conditions.priority', $automation->condition('priority')) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Deal value at least (₹)
                    <input type="number" name="conditions[min_value]" value="{{ old('conditions.min_value', $automation->condition('min_value')) }}" min="1" placeholder="Any value">
                </label>
                <label>City is <input name="conditions[city]" value="{{ old('conditions.city', $automation->condition('city')) }}" maxlength="100" placeholder="Any city"></label>
                @if ($fields->isNotEmpty())
                    <div class="field-pair">
                        <label>Field
                            <select name="conditions[field_key]">
                                <option value="">No field</option>
                                @foreach ($fields as $field)
                                    <option value="{{ $field->key }}" @selected(old('conditions.field_key', $automation->condition('field_key')) === $field->key)>{{ $field->label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>is <input name="conditions[field_value]" value="{{ old('conditions.field_value', $automation->condition('field_value')) }}" maxlength="150" placeholder="e.g. 2BHK"></label>
                    </div>
                @endif
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
                <label>Set priority
                    <select name="actions[set_priority]">
                        <option value="">Don't change</option>
                        @foreach (\App\Enums\Priority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected(old('actions.set_priority', $automation->action('set_priority')) === $priority->value)>{{ $priority->label() }}</option>
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
                <label>Start sequence
                    <select name="actions[start_sequence_id]">
                        <option value="">None</option>
                        @foreach ($sequences as $sequence)
                            <option value="{{ $sequence->id }}" @selected(old('actions.start_sequence_id', $automation->action('start_sequence_id')) == $sequence->id)>{{ $sequence->name }}</option>
                        @endforeach
                    </select>
                    @if ($sequences->isEmpty())<span class="muted small">Create one under Settings → Sequences.</span>@endif
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

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    // Show the extra trigger fields only when they apply.
    (function () {
        const trigger = document.querySelector('[data-trigger]');
        const sync = () => {
            const option = trigger.selectedOptions[0];
            document.querySelectorAll('[data-when]').forEach((field) => {
                field.hidden = field.dataset.when === 'scheduled' ? !option.dataset.unit : field.dataset.when !== trigger.value;
            });
            document.querySelector('[data-unit-label]').textContent = option.dataset.unit || 'days';
        };
        trigger.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
