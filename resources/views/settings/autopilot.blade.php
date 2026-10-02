@extends('layouts.settings')
@section('guide', 'autopilot')

@section('settings')
    @php($switches = collect(\App\Autopilot\AutopilotSettings::DEFAULTS)->filter(fn ($v) => is_bool($v))->keys()->reject(fn ($k) => $k === 'work_sundays'))
    @php($on = $switches->filter(fn ($k) => $settings->on($k))->count())
    @php($num = fn (string $key, int $min, int $max, string $label) => '<input type="number" class="inline-num" name="'.$key.'" value="'.e(old($key, $settings->number($key))).'" min="'.$min.'" max="'.$max.'" required aria-label="'.e($label).'">')
    @php($noWhatsApp = $whatsapp ? null : 'Connect WhatsApp under Integrations to use this.')

    <div class="settings-head">
        <div>
            <h2>Autopilot</h2>
            <p>Routine work the CRM does on its own, so no lead waits on someone remembering. Every step it takes is written in the lead's history.</p>
        </div>
        <span class="pill ok nowrap">{{ $on }} of {{ $switches->count() }} on</span>
    </div>

    <form method="post" action="{{ route('settings.autopilot.update') }}">
        @csrf @method('put')

        <section class="card auto-group">
            <h3>Working hours</h3>
            <div class="auto-hours">
                <label class="inline">From
                    <select name="work_start" class="inline-select">
                        @for ($h = 0; $h <= 23; $h++)<option value="{{ $h }}" @selected(old('work_start', $settings->number('work_start')) == $h)>{{ sprintf('%02d:00', $h) }}</option>@endfor
                    </select>
                </label>
                <label class="inline">to
                    <select name="work_end" class="inline-select">
                        @for ($h = 1; $h <= 24; $h++)<option value="{{ $h }}" @selected(old('work_end', $settings->number('work_end')) == $h)>{{ $h === 24 ? 'midnight' : sprintf('%02d:00', $h) }}</option>@endfor
                    </select>
                </label>
                <label class="check"><input type="checkbox" name="work_sundays" value="1" @checked($errors->any() ? old('work_sundays') : $settings->on('work_sundays'))>Open on Sundays</label>
            </div>
            <p class="hint" style="margin:0 0 14px">In your workspace's time zone ({{ auth()->user()->organization->timezone }}). Autopilot only passes on leads, sends nudges and closes leads inside these hours; the away message covers the rest.</p>
        </section>

        <section class="card auto-group">
            <h3>New leads</h3>
            <x-autopilot-switch name="first_follow_up" title="Plan the first call" :settings="$settings">
                A new lead is due for a follow-up within {!! $num('first_follow_up_minutes', 0, 1440, 'Minutes') !!} minutes, so it shows in My day and its owner gets a reminder. Leads that come in after hours are due just after opening.
            </x-autopilot-switch>
            <x-autopilot-switch name="speed_to_lead" title="Pass on unanswered leads" :settings="$settings">
                If nobody contacts a lead from a form, ad, WhatsApp or the API within {!! $num('speed_to_lead_minutes', 5, 1440, 'Minutes') !!} minutes, give it to the next agent and alert admins.
            </x-autopilot-switch>
        </section>

        <section class="card auto-group">
            <h3>Follow-ups</h3>
            <x-autopilot-switch name="next_follow_up" title="Always plan the next step" :settings="$settings">
                When a follow-up is saved without a date and the lead is still open, plan the next one {!! $num('next_follow_up_days', 1, 30, 'Days') !!} days later at 11:00.
            </x-autopilot-switch>
            <x-autopilot-switch name="contacted_on_first_message" title="Mark leads as contacted" :settings="$settings" :unavailable="$noWhatsApp">
                When someone sends the first WhatsApp message to a lead in the first stage, move it to the next stage.
            </x-autopilot-switch>
            <x-autopilot-switch name="reopen_returning" title="Bring back returning leads" :settings="$settings">
                When a lost lead enquires again or writes on WhatsApp, reopen it, make it due now and alert its owner.
            </x-autopilot-switch>
        </section>

        <section class="card auto-group">
            <h3>Quiet leads</h3>
            <x-autopilot-switch name="reengage" title="Nudge quiet leads" :settings="$settings">
                When an open lead has been quiet for {!! $num('reengage_days', 3, 90, 'Days') !!} days,
                @if ($whatsapp)
                    send
                    <select name="reengage_template_id" class="inline-select" aria-label="WhatsApp template">
                        <option value="">no message</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('reengage_template_id', $settings->get('reengage_template_id')) == $template->id)>{{ $template->label() }}</option>
                        @endforeach
                    </select>
                    on WhatsApp and
                @endif
                remind its owner to check in. Once per quiet spell.
            </x-autopilot-switch>
            <x-autopilot-switch name="auto_close" title="Close dead leads" :settings="$settings">
                Mark open leads with no follow-ups and no messages for {!! $num('auto_close_days', 14, 365, 'Days') !!} days as Lost, so the pipeline shows only real chances.
            </x-autopilot-switch>

            <x-autopilot-switch name="win_back" title="Win back lost leads" :settings="$settings">
                Reopen a lost lead for its owner once the delay set on its <a href="{{ route('settings.lost-reasons.index') }}">lost reason</a> has passed (for example 30 days for "Price too high")@if ($whatsapp), and send
                    <select name="win_back_template_id" class="inline-select" aria-label="Win-back template">
                        <option value="">no message</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('win_back_template_id', $settings->get('win_back_template_id')) == $template->id)>{{ $template->label() }}</option>
                        @endforeach
                    </select>@endif. Once per lead.
            </x-autopilot-switch>
        </section>

        <section class="card auto-group">
            <h3>WhatsApp and AI</h3>
            <x-autopilot-switch name="away_message" title="Away message" :settings="$settings" :unavailable="$noWhatsApp">
                Outside working hours, reply once (at most every 12 hours) with:
                <label class="sr-only" for="away_text">Away message</label>
                <textarea name="away_text" id="away_text" rows="2" maxlength="500" class="auto-textarea" required>{{ old('away_text', $settings->get('away_text')) }}</textarea>
            </x-autopilot-switch>
            <x-autopilot-switch name="meeting_reminders" title="Meeting reminders" :settings="$settings">
                Remind the owner an hour before a booked meeting.
                @if ($whatsapp)
                    Also send the lead
                    <select name="meeting_template_id" class="inline-select" aria-label="Meeting reminder template">
                        <option value="">no message</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('meeting_template_id', $settings->get('meeting_template_id')) == $template->id)>{{ $template->label() }}</option>
                        @endforeach
                    </select>
                    a day and an hour before. In the template, @{{1}} is their first name, @{{2}} the date and time, @{{3}} the place.
                @endif
            </x-autopilot-switch>
            <x-autopilot-switch name="ai_on_reply" title="AI follow-through" :settings="$settings" :unavailable="$aiUnavailable">
                When a lead writes to you, refresh its AI summary and mark hot leads as high priority so they are called first.
            </x-autopilot-switch>
            <x-autopilot-switch name="voice_notes" title="Write down voice notes" :settings="$settings" :unavailable="$voiceUnavailable">
                When a lead sends a voice note, write down what they said (Hindi, Hinglish or English) with a one-line summary, so you can read it instead of listening. Each voice note uses one AI analysis.
            </x-autopilot-switch>
        </section>

        <section class="card auto-group">
            <h3>Team</h3>
            <x-autopilot-switch name="share_leads_of_leavers" title="Hand over leads when someone leaves" :settings="$settings">
                When a team member is deactivated or removed, share their open leads among the other agents.
            </x-autopilot-switch>
            <x-autopilot-switch name="daily_digest" title="Morning summary" :settings="$settings">
                At 9:00 everyone gets an email with today's follow-ups. Admins also see who has overdue follow-ups.
            </x-autopilot-switch>
            <x-autopilot-switch name="weekly_report" title="Monday report" :settings="$settings">
                Every Monday at 9:00, admins get last week's numbers: new leads, response time and deals won.
            </x-autopilot-switch>
        </section>

        <div class="card tip-card">
            <x-icon name="check-circle"/>
            <span>Always automatic: leads from your forms, ads and WhatsApp land here by themselves, repeat enquiries join the existing lead, new leads are shared round-robin, reminders go out when follow-ups are due, WhatsApp templates sync every night and your data is backed up daily.</span>
        </div>

        <div class="form-actions sticky-actions">
            <button type="submit" class="btn primary">Save Autopilot</button>
            <span class="muted small">Changes apply right away.</span>
        </div>
    </form>
@endsection
