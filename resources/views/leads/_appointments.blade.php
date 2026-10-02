{{-- Meetings with this lead: what's booked, how past ones went, and booking a new one. --}}
<section class="card">
    <div class="card-head" style="margin-bottom:10px"><h2>Meetings</h2></div>
    @foreach ($appointments as $appointment)
        <div @class(['meeting', 'needs-outcome' => $appointment->awaitsOutcome()])>
            <span class="n-icon"><x-icon name="calendar"/></span>
            <div class="grow">
                <strong>{{ $appointment->type->label() }}</strong>
                <span class="muted small">{{ $appointment->when() }}{{ $appointment->location ? ' · '.$appointment->location : '' }}</span>
                @can('update', $lead)
                    <form method="post" action="{{ route('appointments.update', $appointment) }}" class="meeting-actions">
                        @csrf @method('patch')
                        @if ($appointment->awaitsOutcome())
                            <span class="small">How did it go?</span>
                            <button type="submit" name="outcome" value="done" class="btn small">Done</button>
                            <button type="submit" name="outcome" value="no_show" class="btn small">No-show</button>
                        @else
                            <button type="submit" name="outcome" value="cancelled" class="link danger small">Cancel meeting</button>
                        @endif
                    </form>
                @endcan
            </div>
        </div>
    @endforeach

    @can('update', $lead)
        <details class="book" @if ($errors->has('starts_at') || $appointments->isEmpty()) open @endif>
            <summary class="btn small"><x-icon name="plus"/>Book a meeting</summary>
            <form method="post" action="{{ route('leads.appointments.store', $lead) }}" class="stack" style="gap:10px; margin-top:12px">
                @csrf
                <label>Type
                    <select name="type">
                        @foreach (\App\Enums\AppointmentType::cases() as $type)<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>@endforeach
                    </select>
                </label>
                <label>When <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required></label>
                <label>Where <input name="location" value="{{ old('location') }}" maxlength="150" placeholder="e.g. Pune office, or a meeting link"></label>
                <button type="submit" class="btn primary block">Book</button>
                <span class="hint">The lead gets a WhatsApp reminder a day and an hour before, if a reminder template is set under Autopilot.</span>
            </form>
        </details>
    @endcan
</section>
