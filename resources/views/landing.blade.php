<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <title>{{ config('app.name') }} — the simple CRM for WhatsApp-first sales teams</title>
    <meta name="description" content="Capture leads from WhatsApp, your website, Facebook and Google ads. Assign them instantly, follow up on time and see what converts. {{ $trialDays }}-day free trial.">
    <meta property="og:title" content="{{ config('app.name') }} — never lose a lead again">
    <meta property="og:description" content="One simple CRM for every lead from WhatsApp, your website and ads.">
    <meta property="og:image" content="{{ url('/images/app-dashboard.webp') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="landing">
@php($shot = fn (string $name) => file_exists(public_path("images/{$name}.webp")) ? asset("images/{$name}.webp") : null)

@include('partials.public-nav')
@include('partials.flash')

<main>
    <section class="l-hero">
        <div class="l-container">
            <span class="l-eyebrow">Built for sales teams that live on WhatsApp</span>
            <h1>Never lose a lead again.</h1>
            <p class="l-lead">One simple CRM for every lead from WhatsApp, your website, Facebook and Google ads — assigned to the right person instantly and followed up on time.</p>
            <div class="l-cta">
                <a href="{{ route('register') }}" class="btn primary large">Start your free trial<x-icon name="arrow-right"/></a>
                <a href="#pricing" class="btn large">See pricing</a>
            </div>
            <p class="l-micro"><x-icon name="check" class="icon sm"/>{{ $trialDays }}-day free trial <x-icon name="check" class="icon sm"/>No credit card <x-icon name="check" class="icon sm"/>Works on your phone</p>
            @if ($src = $shot('app-dashboard'))
                <div class="l-frame">
                    <div class="l-frame-bar"><span></span><span></span><span></span></div>
                    <img src="{{ $src }}" alt="The {{ config('app.name') }} dashboard showing follow-ups due, the pipeline and team performance" width="1440" height="900">
                </div>
            @endif
        </div>
    </section>

    <section class="l-sources">
        <div class="l-container">
            <p>Leads flow in automatically from</p>
            <ul>
                <li><x-icon name="whatsapp"/>WhatsApp</li>
                <li><x-icon name="megaphone"/>Facebook &amp; Instagram ads</li>
                <li><x-icon name="search-ad"/>Google Ads</li>
                <li><x-icon name="globe"/>Your website</li>
                <li><x-icon name="file"/>Excel / CSV</li>
                <li><x-icon name="code"/>API</li>
            </ul>
        </div>
    </section>

    <section class="l-section" id="features">
        <div class="l-container">
            <div class="l-heading">
                <span class="l-eyebrow">Everything a sales team needs</span>
                <h2>Simple enough to use every day. Powerful enough to grow with you.</h2>
                <p>No bloated menus and no week-long setup. Just the tools that turn enquiries into customers.</p>
            </div>
            <div class="l-features">
                <article><span class="l-icon"><x-icon name="inbox"/></span><h3>Every lead in one place</h3><p>Website, WhatsApp, ads and spreadsheets land in one list. When someone enquires again, it's added to their history — never lost as a duplicate.</p></article>
                <article><span class="l-icon"><x-icon name="users"/></span><h3>Instant, fair assignment</h3><p>New leads go to your agents in turn, the second they arrive, and each agent is notified straight away. Speed to lead, built in.</p></article>
                <article><span class="l-icon"><x-icon name="clock"/></span><h3>Follow-ups that happen</h3><p>Log a call outcome in one tap, pick the next date, and the CRM reminds the agent in the app and by email when it's due.</p></article>
                <article><span class="l-icon"><x-icon name="zap"/></span><h3>Automations</h3><p>“When a Facebook lead arrives, assign it to Priya, send the welcome message and schedule a call in 2 hours.” Set once, runs forever.</p></article>
                <article><span class="l-icon"><x-icon name="reports"/></span><h3>Reports that matter</h3><p>See your funnel, how fast each agent responds, and which sources actually win deals — not just which bring the most leads.</p></article>
                <article><span class="l-icon"><x-icon name="sparkles"/></span><h3>AI assistant</h3><p>One click summarises a lead, scores it hot, warm or cold, and drafts the next WhatsApp message in the lead's own language.</p></article>
            </div>
        </div>
    </section>

    <section class="l-section l-tint" id="whatsapp">
        <div class="l-container l-split">
            <div class="l-split-text">
                <span class="l-eyebrow">WhatsApp, done properly</span>
                <h2>Your WhatsApp conversations, inside your CRM.</h2>
                <p>Connect your business number through the official WhatsApp Cloud API. Every chat sits next to the lead it belongs to, so nothing gets lost in someone's phone.</p>
                <ul class="l-checks">
                    <li><x-icon name="check-circle"/>Two-way chat with read receipts</li>
                    <li><x-icon name="check-circle"/>New numbers become leads automatically</li>
                    <li><x-icon name="check-circle"/>Approved templates to start or restart conversations</li>
                    <li><x-icon name="check-circle"/>A shared inbox with unread counts for the whole team</li>
                </ul>
            </div>
            @if ($src = $shot('app-inbox'))
                <div class="l-frame small"><img src="{{ $src }}" alt="The WhatsApp inbox with a conversation open" loading="lazy" width="1440" height="900"></div>
            @endif
        </div>
    </section>

    <section class="l-section">
        <div class="l-container l-split reverse">
            <div class="l-split-text">
                <span class="l-eyebrow">One page per lead</span>
                <h2>Know exactly where every deal stands.</h2>
                <p>Contact details, pipeline stage, every call and message, and the next step — on one screen. The AI assistant tells you how warm the lead is and what to say next.</p>
                <ul class="l-checks">
                    <li><x-icon name="check-circle"/>Your own pipeline stages and custom fields</li>
                    <li><x-icon name="check-circle"/>Full history of calls, notes and messages</li>
                    <li><x-icon name="check-circle"/>Agents see only their own leads</li>
                </ul>
            </div>
            @if ($src = $shot('app-lead'))
                <div class="l-frame small"><img src="{{ $src }}" alt="A lead page with history, AI insight and details" loading="lazy" width="1440" height="900"></div>
            @endif
        </div>
    </section>

    <section class="l-section l-tint">
        <div class="l-container">
            <div class="l-heading">
                <span class="l-eyebrow">Up and running today</span>
                <h2>Three steps to a team that never forgets a lead.</h2>
            </div>
            <ol class="l-steps">
                <li><span>1</span><h3>Create your workspace</h3><p>Sign up in a minute and add your team. Your pipeline and lead sources come ready to use.</p></li>
                <li><span>2</span><h3>Connect your sources</h3><p>Share your lead form, connect WhatsApp and your ad accounts, or import a spreadsheet.</p></li>
                <li><span>3</span><h3>Follow up and close</h3><p>Leads are assigned automatically, reminders keep everyone on time, and reports show what works.</p></li>
            </ol>
        </div>
    </section>

    <section class="l-section" id="pricing">
        <div class="l-container">
            <div class="l-heading">
                <span class="l-eyebrow">Pricing</span>
                <h2>Simple plans. No per-feature surprises.</h2>
                <p>Every plan starts with a {{ $trialDays }}-day free trial of everything. Prices in rupees per month, excluding GST.</p>
            </div>
            <div class="plans l-plans">
                @foreach ($plans as $plan)
                    @php($recommended = $plan->key === 'growth')
                    <section @class(['card', 'plan', 'recommended' => $recommended])>
                        @if ($recommended)<span class="ribbon">Recommended</span>@endif
                        <div><h3>{{ $plan->name }}</h3><div class="muted small">Up to {{ $plan->maxUsers }} users</div></div>
                        <div class="price">₹{{ number_format($plan->price) }}<span> / month</span></div>
                        <ul>
                            @foreach (config('plans.included') as $item)
                                <li><x-icon name="check"/>{{ $item }}</li>
                            @endforeach
                            @foreach ($plan->features as $feature)
                                <li><x-icon name="check"/><strong>{{ $feature->label() }}</strong></li>
                            @endforeach
                        </ul>
                        <a href="{{ route('register') }}" @class(['btn', 'block', 'primary' => $recommended])>Start free trial</a>
                    </section>
                @endforeach
            </div>
        </div>
    </section>

    <section class="l-section l-tint" id="faq">
        <div class="l-container l-faq">
            <div class="l-heading"><span class="l-eyebrow">FAQ</span><h2>Questions, answered.</h2></div>
            <details><summary>Do I need a credit card to try it?</summary><p>No. You get {{ $trialDays }} days with every feature. Choose a plan whenever you're ready — your leads and settings stay exactly as they are.</p></details>
            <details><summary>Is the WhatsApp integration official?</summary><p>Yes. It uses Meta's official WhatsApp Cloud API with your own business number, so there's no risk of the number being banned for using unofficial tools. Meta's own message charges apply.</p></details>
            <details><summary>Can I bring my existing leads?</summary><p>Yes. Import a CSV exported from Excel or Google Sheets. Duplicate phone numbers are skipped automatically, and you can export everything again at any time.</p></details>
            <details><summary>Who can see our leads?</summary><p>Only your team. Every workspace is completely separate, agents see only the leads assigned to them, and connection passwords for WhatsApp and ads are stored encrypted.</p></details>
            <details><summary>Does it work on mobile?</summary><p>Yes. It works in any phone browser and can be added to your home screen like an app, with one-tap call, WhatsApp and follow-up logging.</p></details>
            <details><summary>Can I change or cancel my plan?</summary><p>Any time, from Settings → Billing. If you cancel, you keep access until the end of the period you've paid for.</p></details>
        </div>
    </section>

    <section class="l-final">
        <div class="l-container">
            <h2>Start following up on every lead today.</h2>
            <p>Set up in minutes. Free for {{ $trialDays }} days.</p>
            <a href="{{ route('register') }}" class="btn large l-white">Create your workspace<x-icon name="arrow-right"/></a>
        </div>
    </section>
</main>

@include('partials.public-footer')
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
