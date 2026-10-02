{{-- "How did the call go?" Opens when someone taps a Call button (tel: links with data-call); works through public/js/app.js. --}}
<dialog class="palette call-sheet" id="call-sheet" aria-labelledby="call-sheet-title">
    <form method="post" class="call-form">
        @csrf
        <input type="hidden" name="seconds" value="">
        <div class="call-head">
            <span class="call-icon"><x-icon name="phone"/></span>
            <div class="grow"><h2 id="call-sheet-title">How did the call with <span data-call-name>them</span> go?</h2><p class="muted small">Calling… <span data-call-timer>0:00</span>. Tap one when you hang up.</p></div>
            <button type="button" class="icon-btn" data-close aria-label="I didn't call"><x-icon name="x"/></button>
        </div>
        <a href="#" class="call-talked" data-call-talked autofocus><x-icon name="check-circle"/><span><strong>We talked</strong><span>Write what they said and pick the next step</span></span><x-icon name="chevron-right"/></a>
        <div class="call-outcomes">
            @foreach ([\App\Calls\CallOutcome::NoAnswer, \App\Calls\CallOutcome::Busy, \App\Calls\CallOutcome::SwitchedOff, \App\Calls\CallOutcome::WrongNumber] as $outcome)
                <button type="submit" name="outcome" value="{{ $outcome->value }}" class="btn">{{ $outcome->label() }}</button>
            @endforeach
        </div>
        <div class="call-back">
            <span class="muted small">They asked me to call back:</span>
            <input type="hidden" name="call_back" value="">
            @foreach (['1h' => 'In an hour', 'evening' => 'This evening', 'tomorrow' => 'Tomorrow'] as $when => $label)
                <button type="submit" name="outcome" value="call_back" data-call-back="{{ $when }}" class="btn small">{{ $label }}</button>
            @endforeach
        </div>
        <input name="note" maxlength="500" placeholder="Note (optional)" aria-label="Note" class="call-note">
        <p class="hint" style="margin:0">No answer tries again in 2 hours, busy in 1 hour, switched off tomorrow. It's all saved in the lead's history.</p>
    </form>
</dialog>
