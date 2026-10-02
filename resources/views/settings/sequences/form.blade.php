@extends('layouts.settings')

@section('settings')
    @php($rows = $sequence->steps->map(fn ($step) => ['day' => $step->day, 'action' => $step->action->value, 'whatsapp_template_id' => $step->whatsapp_template_id, 'note' => $step->note])->all())
    @php($rows = old('steps', $rows))
    @php($rows = array_slice(array_pad(array_values($rows), max(count($rows) + 2, 4), []), 0, \App\Http\Requests\SequenceRequest::MAX_STEPS))
    <div class="settings-head">
        <div>
            <a href="{{ route('settings.sequences.index') }}" class="back"><x-icon name="arrow-left" class="icon sm"/>Sequences</a>
            <h2>{{ $sequence->exists ? 'Edit sequence' : 'New sequence' }}</h2>
            <p>Each step runs on its day, counted from when the lead joins. Day 0 runs right away (inside working hours).</p>
        </div>
    </div>

    <form method="post" action="{{ $sequence->exists ? route('settings.sequences.update', $sequence) : route('settings.sequences.store') }}" class="card stack">
        @csrf
        @if ($sequence->exists) @method('put') @endif

        <label>Name <input name="name" value="{{ old('name', $sequence->name) }}" required maxlength="100" placeholder="e.g. New lead welcome series"></label>
        <label class="check"><input type="checkbox" name="stop_on_reply" value="1" @checked(old('stop_on_reply', $sequence->stop_on_reply))>Stop when the lead replies on WhatsApp</label>

        <fieldset>
            <legend>Steps</legend>
            <div class="step-rows">
                <div class="step-row head"><span>Day</span><span>What happens</span><span>Details</span></div>
                @foreach ($rows as $i => $row)
                    <div class="step-row" data-step-row>
                        <input type="number" name="steps[{{ $i }}][day]" value="{{ $row['day'] ?? '' }}" min="0" max="90" placeholder="{{ $i === 0 ? '0' : '' }}" aria-label="Day of step {{ $i + 1 }}">
                        <select name="steps[{{ $i }}][action]" aria-label="What happens in step {{ $i + 1 }}" data-step-action>
                            <option value="">No step</option>
                            @foreach (\App\Enums\SequenceStepAction::cases() as $action)
                                <option value="{{ $action->value }}" @selected(($row['action'] ?? '') === $action->value)>{{ $action->label() }}</option>
                            @endforeach
                        </select>
                        <span class="step-detail">
                            <select name="steps[{{ $i }}][whatsapp_template_id]" aria-label="Template for step {{ $i + 1 }}" data-for="{{ \App\Enums\SequenceStepAction::WhatsAppTemplate->value }}">
                                <option value="">Choose a template…</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" @selected(($row['whatsapp_template_id'] ?? '') == $template->id)>{{ $template->label() }}</option>
                                @endforeach
                            </select>
                            <input name="steps[{{ $i }}][note]" value="{{ $row['note'] ?? '' }}" maxlength="200" placeholder="e.g. Call to fix the site visit" aria-label="Reminder for step {{ $i + 1 }}" data-for="{{ \App\Enums\SequenceStepAction::RemindOwner->value }}">
                        </span>
                    </div>
                @endforeach
            </div>
            @if ($templates->isEmpty())<p class="hint" style="margin:10px 0 0">No approved WhatsApp templates yet: connect WhatsApp and sync templates to add message steps. Reminder steps work without WhatsApp.</p>@endif
            <p class="hint" style="margin:8px 0 0">Need more rows? Save, and two empty ones appear (up to {{ \App\Http\Requests\SequenceRequest::MAX_STEPS }} steps). A lead also leaves the sequence when it is won or lost.</p>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn primary">{{ $sequence->exists ? 'Save changes' : 'Save sequence' }}</button>
            <a href="{{ route('settings.sequences.index') }}" class="btn ghost">Cancel</a>
        </div>
    </form>

    @if ($sequence->exists)
        <form method="post" action="{{ route('settings.sequences.destroy', $sequence) }}" data-confirm="Delete this sequence? Leads in it stop getting its steps.">
            @csrf @method('delete')
            <button type="submit" class="btn danger small"><x-icon name="trash"/>Delete sequence</button>
        </form>
    @endif
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    // Show only the detail field the chosen action needs.
    document.querySelectorAll('[data-step-row]').forEach((row) => {
        const action = row.querySelector('[data-step-action]');
        const sync = () => row.querySelectorAll('[data-for]').forEach((field) => { field.hidden = field.dataset.for !== action.value; });
        action.addEventListener('change', sync);
        sync();
    });
</script>
@endpush
