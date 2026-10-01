@extends('layouts.app')
@section('title', 'Help')
@section('subtitle', 'Short guides to everything in '.config('app.name').'.')

@section('content')
    @php($topics = [
        ['getting-started', 'Getting started', 'dashboard', [
            ['How do I set up my workspace?', 'Follow the checklist on your dashboard: add a lead, invite your team, publish your lead form, connect WhatsApp and create an automation. It takes about five minutes.'],
            ['Where do I change my company name or time zone?', 'Settings → Workspace. Follow-up times, reminders and reports all use the workspace time zone.'],
        ]],
        ['leads', 'Adding and working leads', 'leads', [
            ['What are the ways to add leads?', 'By hand (Leads → Add lead), from a spreadsheet (Settings → Import / Export), from your website form, WhatsApp, Facebook/Instagram lead ads, Google Ads lead forms, or the Developer API. All of them assign the lead to an agent automatically.'],
            ['What happens if the same person enquires twice?', 'The phone number is recognised and the new enquiry is added to the existing lead\'s history instead of creating a duplicate.'],
            ['How do I log a call?', 'Open the lead, pick the outcome (for example Contacted or Interested), add a short note and the next follow-up date, and save. Quick buttons set tomorrow, 3 days or next week.'],
            ['What do Fresh, In progress and Dormant mean?', 'Fresh: not contacted yet. In progress: followed up recently. Dormant: open but no follow-up for '.config('crm.dormant_after_days').' days. Won and Lost are closed leads.'],
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
        ['automations', 'Automations and reminders', 'zap', [
            ['What can automations do?', 'When a lead arrives or changes status (optionally only for a source or status), they can assign it, change its status, send a WhatsApp template, schedule a follow-up and notify someone.'],
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
<script>
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
