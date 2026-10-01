@csrf
<div class="form-section">
    <h2>Contact</h2>
    <span class="hint">Who is this lead? Name and phone are required.</span>
    <div class="form-grid">
        <label>Full name <input name="name" value="{{ old('name', $lead->name) }}" required maxlength="150" autofocus></label>
        <label>Phone <input type="tel" name="phone" value="{{ old('phone', $lead->phone) }}" required maxlength="25" placeholder="+91 98765 43210"></label>
        <label>Email <input type="email" name="email" value="{{ old('email', $lead->email) }}" maxlength="150"></label>
        <label>Company <input name="company" value="{{ old('company', $lead->company) }}" maxlength="150"></label>
        <label>City <input name="city" value="{{ old('city', $lead->city) }}" maxlength="100"></label>
        <label>Source
            <select name="source_id">
                <option value="">—</option>
                @foreach ($sources as $source)
                    <option value="{{ $source->id }}" @selected(old('source_id', $lead->source_id) == $source->id)>{{ $source->name }}</option>
                @endforeach
            </select>
        </label>
    </div>
</div>

<div class="form-section">
    <h2>Deal</h2>
    <span class="hint">Where the lead stands and who owns it.</span>
    <div class="form-grid">
        <label>Status
            <select name="status_id" @required($lead->exists)>
                @unless ($lead->exists)<option value="">Default ({{ $statuses->firstWhere('type', \App\Enums\StatusType::Open)?->name }})</option>@endunless
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected(old('status_id', $lead->status_id) == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </label>
        @can('admin')
            <label>Owner
                <select name="assigned_to">
                    <option value="">{{ $lead->exists ? 'Unassigned' : 'Automatic (round-robin)' }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(old('assigned_to', $lead->assigned_to) == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>
        @endcan
        <label>Deal value (₹) <input type="number" name="value" value="{{ old('value', $lead->value) }}" min="0" step="0.01"></label>
        <label>Priority
            <select name="priority" required>
                @foreach (\App\Enums\Priority::cases() as $priority)
                    <option value="{{ $priority->value }}" @selected(old('priority', $lead->priority?->value) === $priority->value)>{{ $priority->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>Next follow-up <input type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at', $lead->next_follow_up_at?->format('Y-m-d\TH:i')) }}"></label>
    </div>
</div>

@if ($customFields->isNotEmpty())
    <div class="form-section">
        <h2>Details</h2>
        <span class="hint">Fields your team added under Settings → Custom fields.</span>
        <div class="form-grid">
            @foreach ($customFields as $field)
                @php($value = old('custom.'.$field->key, $lead->custom_values[$field->key] ?? null))
                <label>{{ $field->label }}{{ $field->is_required ? ' *' : '' }}
                    @switch($field->type)
                        @case(\App\Enums\CustomFieldType::Select)
                            <select name="custom[{{ $field->key }}]" @required($field->is_required)>
                                <option value="">—</option>
                                @foreach ($field->options ?? [] as $option)
                                    <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @break
                        @case(\App\Enums\CustomFieldType::Number)
                            <input type="number" step="any" name="custom[{{ $field->key }}]" value="{{ $value }}" @required($field->is_required)>
                            @break
                        @case(\App\Enums\CustomFieldType::Date)
                            <input type="date" name="custom[{{ $field->key }}]" value="{{ $value }}" @required($field->is_required)>
                            @break
                        @default
                            <input name="custom[{{ $field->key }}]" value="{{ $value }}" maxlength="500" @required($field->is_required)>
                    @endswitch
                </label>
            @endforeach
        </div>
    </div>
@endif

<div class="form-section">
    <h2>Notes</h2>
    <textarea name="notes" rows="4" maxlength="5000" placeholder="Anything worth remembering about this lead">{{ old('notes', $lead->notes) }}</textarea>
</div>
