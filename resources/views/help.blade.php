@extends('layouts.app')
@section('title', 'Help')
@section('subtitle', 'Short guides to everything in '.config('app.name').'.')

@section('content')
    @php($topics = [
        ['getting-started', 'Getting started', 'dashboard', [
            ['How do I set up my workspace?', 'Follow the checklist on your dashboard: add a lead, invite your team, publish your lead form, connect WhatsApp and create an automation. It takes about five minutes. Then look at Settings → Autopilot, where the routine follow-up work is already switched on.'],
            ['Where do I change my company name or time zone?', 'Settings → Workspace. Follow-up times, reminders and reports all use the workspace time zone.'],
        ]],
        ['leads', 'Adding and working leads', 'leads', [
            ['What are the ways to add leads?', 'By hand (Leads → Add lead), from a spreadsheet (Settings → Import / Export), from your website form, WhatsApp, Facebook/Instagram lead ads, Google Ads lead forms, or the Developer API. All of them assign the lead to an agent automatically.'],
            ['What happens if the same person enquires twice?', 'The phone number is recognised and the new enquiry is added to the existing lead\'s history instead of creating a duplicate.'],
            ['How do I log a call?', 'Open the lead, pick the outcome (for example Contacted or Interested), add a short note and the next follow-up date, and save. Quick buttons set tomorrow, 3 days or next week.'],
            ['How are new leads shared out?', 'By default agents take turns. Under Settings → Lead routing you can add rules, for example "Facebook leads from Pune go to Asha and Ravi", and set a limit on open leads per person. Anyone can pause their own new leads from their name at the bottom left ("I\'m away"); they are skipped until they\'re back.'],
            ['What do Fresh, In progress and Dormant mean?', 'Fresh: not contacted yet. In progress: followed up recently. Dormant: open but no follow-up for '.config('crm.dormant_after_days').' days. Won and Lost are closed leads.'],
            ['How do I book a meeting or site visit?', 'Open the lead and use Book a meeting in the Meetings card. It becomes the lead\'s next follow-up and shows on everyone\'s dashboard that day. Pick a reminder template under Settings → Autopilot (Meeting reminders) and the lead gets a WhatsApp reminder a day and an hour before; the owner is reminded an hour before. Afterwards, mark it done or no-show.'],
            ['Why does it ask why a lead was lost?', 'So Reports can show which reasons cost you most (price, competitors, timing…). Edit the list under Settings → Lost reasons. Each reason can also have a win-back delay: turn on Win back lost leads under Autopilot and, for example, a lead lost on price comes back to its owner after 30 days with a WhatsApp message.'],
            ['What is the lead score?', 'A number from 0 to 100 that shows how likely a lead is to buy: 70 and above is hot, 40 to 69 warm, below 40 cold. Recent WhatsApp replies, recent follow-ups, a later stage, a bigger deal, a source that usually converts, high priority and a hot AI rating raise it; going quiet lowers it. Open a lead to see exactly why it has its score, and sort the lead list by "Highest score" to call the best leads first.'],
            ['Can I add my own fields?', 'Yes. Settings → Custom fields: text, number, date or dropdown. They appear on every lead and in imports and exports.'],
        ]],
        ['whatsapp', 'WhatsApp', 'whatsapp', [
            ['How do I connect WhatsApp?', 'Settings → Integrations → WhatsApp Business. Follow the steps on that page, save, then click Test connection. You need a Meta Business account and a number that is not already using the WhatsApp app.'],
            ['Why can\'t I type a free message?', 'WhatsApp allows free messages only within 24 hours of the lead\'s last message. Outside that window, send an approved template; when the lead replies, free messages open again.'],
            ['How do I greet every new lead automatically?', 'Create an automation: "When a new lead arrives → send WhatsApp template".'],
        ]],
        ['ads', 'Facebook, Instagram and Google ads', 'megaphone', [
            ['How do ad leads arrive?', 'Once connected under Settings → Integrations, leads from your ad forms appear within seconds, with the form answers saved in the lead\'s notes.'],
            ['How do I test the connection?', 'Facebook: use Meta\'s Lead Ads Testing Tool. Google: click "Send test data" in the lead form\'s webhook settings.'],
        ]],
        ['automations', 'Autopilot, automations and reminders', 'zap', [
            ['What does Autopilot do?', 'Routine work, on its own: it plans the first call for new leads, passes on leads nobody answered in time, plans the next follow-up when you forget the date, moves leads to Contacted after your first WhatsApp message, reopens lost leads that come back, nudges quiet leads, can close dead ones, replies when you are away, hands over leads when someone leaves, and sends a 9:00 summary every morning. Turn each one on or off under Settings → Autopilot.'],
            ['How do I know what Autopilot did?', 'Every step appears in the lead\'s history, marked Autopilot, and in Settings → Activity log.'],
            ['Autopilot or automations?', 'Autopilot covers the common jobs with one switch each. Automations are your own "when this happens, do that" rules for anything specific, for example sending a template to leads from one source.'],
            ['What is a sequence?', 'A series of follow-ups that runs by itself over several days, for example: day 0 send the welcome template, day 2 remind the owner to call, day 7 send an offer. Build them under Settings → Sequences, then start one from a lead, from the lead list (select leads, then Start sequence) or automatically with an automation. It stops by itself when the lead replies (if you choose) or is won or lost, and only runs inside working hours.'],
            ['What can automations do?', 'They run when a lead arrives, changes status, sends a WhatsApp message (optionally only when it mentions words like "price"), has been quiet for some days, or has a follow-up overdue by some hours. You can limit them by source, status, priority, deal value, city or a custom field. They can assign the lead, change its status or priority, send a WhatsApp template, schedule a follow-up, start a sequence or notify someone.'],
            ['When do reminders arrive?', 'When a follow-up is due, the assigned agent gets an in-app notification, an email and, if turned on, a phone notification.'],
        ]],
        ['team', 'Team and security', 'users', [
            ['How do I add my team?', 'Settings → Team → Invite by email. They choose their own password. You can also copy the invite link and send it on WhatsApp.'],
            ['What can agents see?', 'Only the leads assigned to them. Admins see everything and manage settings.'],
            ['How do I turn on two-factor login?', 'Your name (bottom left) → Security → Set up two-factor login, and scan the QR code with Google or Microsoft Authenticator. Admins can require it for everyone under Settings → Workspace.'],
            ['Where can I see who changed what?', 'Settings → Activity log.'],
        ]],
        ['mobile', 'Using it on your phone', 'smartphone', [
            ['Is there an app?', 'Open the site in your phone\'s browser and choose "Add to Home Screen" (Safari) or "Install app" (Chrome). It opens like an app.'],
            ['How do I get notifications on my phone?', 'Notifications → "Turn on for this device", and allow notifications when asked. On iPhone, add the app to your home screen first.'],
        ]],
        ['data', 'Your data', 'download', [
            ['How do I download everything?', 'Settings → Workspace → Your data. You get a ZIP of spreadsheets: leads, follow-ups, messages, team, settings and the activity log.'],
            ['How do I close my account?', 'Settings → Workspace → Delete workspace. This permanently deletes every lead, message and user, so download your data first.'],
        ]],
    ])

    <div class="help-grid">
        <nav class="settings-nav hide-sm">
            @foreach ($topics as [$id, $title, $icon])
                <a href="#{{ $id }}"><x-icon :name="$icon"/>{{ $title }}</a>
            @endforeach
        </nav>
        <div>
            <div class="filters"><div class="search-field"><x-icon name="search"/><input type="search" id="help-search" placeholder="Search help, e.g. WhatsApp template" aria-label="Search help"></div></div>
            @foreach ($topics as [$id, $title, $icon, $questions])
                <section class="card help-topic" id="{{ $id }}">
                    <div class="card-head"><div class="row"><span class="kpi-icon"><x-icon :name="$icon"/></span><h2>{{ $title }}</h2></div></div>
                    @foreach ($questions as [$question, $answer])
                        <details class="help-q">
                            <summary>{{ $question }}</summary>
                            <p>{{ $answer }}</p>
                        </details>
                    @endforeach
                </section>
            @endforeach
            <section class="card row-between">
                <div><strong>Still stuck?</strong><div class="muted small">We usually reply within one working day.</div></div>
                @if (config('crm.support_email'))
                    <a href="mailto:{{ config('crm.support_email') }}" class="btn primary"><x-icon name="mail"/>Email support</a>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    document.getElementById('help-search').addEventListener('input', (event) => {
        const term = event.target.value.trim().toLowerCase();
        document.querySelectorAll('.help-topic').forEach((topic) => {
            let visible = 0;
            topic.querySelectorAll('.help-q').forEach((q) => {
                const match = !term || q.textContent.toLowerCase().includes(term);
                q.hidden = !match;
                q.open = !!term && match;
                visible += match ? 1 : 0;
            });
            topic.hidden = visible === 0;
        });
    });
</script>
@endpush
