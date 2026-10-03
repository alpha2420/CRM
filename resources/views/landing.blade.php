<!doctype html>
<html lang="en" data-theme-locked>
<head>
    @include('partials.head')
    <title>{{ config('app.name') }}: the WhatsApp-first CRM for Indian businesses</title>
    <meta name="description" content="Capture leads from WhatsApp, your website, IndiaMART, Facebook and Google ads. Assign them instantly, follow up on time and see what converts. {{ $trialDays }}-day free trial.">
    <link rel="canonical" href="{{ url('/') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ config('app.name') }}: the WhatsApp-first CRM for Indian businesses">
    <meta property="og:description" content="Every lead from WhatsApp, ads and IndiaMART in one place. Reply in 5 minutes, follow up on time. {{ $trialDays }}-day free trial.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ config('app.name') }}: the WhatsApp-first CRM for Indian businesses">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/landing.css') }}">
</head>
<body class="landing">
{{-- A browser window around one of our own app screenshots. --}}
@php($window = fn (string $name, string $path, string $alt, bool $lazy = true) => '<figure class="l-window"><div class="l-window-bar"><span></span><span></span><span></span><code>useconvera.com'.e($path).'</code></div><img src="'.e(asset("images/{$name}.webp")).'" alt="'.e($alt).'" width="1440" height="900"'.($lazy ? ' loading="lazy"' : '').'></figure>')

<a class="l-announce" href="#voice-notes">
    <span class="l-announce-tag">New</span> Voice notes on WhatsApp are now written down for you <x-icon name="arrow-right" class="icon sm"/>
</a>
@include('partials.public-nav')
@include('partials.flash')

<main>
    {{-- Hero ------------------------------------------------------------- --}}
    <section class="l-hero">
        <div class="l-hero-water" aria-hidden="true"></div>
        <div class="l-container l-hero-inner">
            <span class="l-chip"><x-icon name="whatsapp" class="icon sm"/>The WhatsApp-first CRM for India</span>
            <h1>Never lose a <em>lead</em> again.</h1>
            <p class="l-lead">Every enquiry from WhatsApp, your website, IndiaMART, Facebook and Google ads lands in one place, goes to the right person in seconds and gets followed up on time.</p>
            <div class="l-cta">
                <a href="{{ route('register') }}" class="l-btn dark">Start your free trial</a>
                <a href="#how" class="l-btn light">See how it works <x-icon name="arrow-right" class="icon sm"/></a>
            </div>
            <p class="l-micro"><span><x-icon name="check" class="icon sm"/>{{ $trialDays }}-day free trial</span><span><x-icon name="check" class="icon sm"/>No credit card</span><span><x-icon name="check" class="icon sm"/>Works on your phone</span></p>

            {{-- "Watch a lead arrive": one tab per source, no JavaScript needed. --}}
            <div class="l-demo">
                <input type="radio" name="l-src" id="src-wa" class="sr-only" checked>
                <input type="radio" name="l-src" id="src-web" class="sr-only">
                <input type="radio" name="l-src" id="src-fb" class="sr-only">
                <input type="radio" name="l-src" id="src-im" class="sr-only">
                <input type="radio" name="l-src" id="src-csv" class="sr-only">
                <div class="l-demo-tabs">
                    <label for="src-wa"><x-icon name="whatsapp"/>WhatsApp</label>
                    <label for="src-web"><x-icon name="globe"/>Website form</label>
                    <label for="src-fb"><x-icon name="megaphone"/>Facebook ads</label>
                    <label for="src-im"><x-icon name="building"/>IndiaMART</label>
                    <label for="src-csv"><x-icon name="file"/>Excel</label>
                </div>
                <div class="l-demo-panels">
                    @foreach ([
                        'src-wa' => ['icon' => 'whatsapp', 'from' => 'Pooja Desai · +91 90000 12345', 'body' => '“Hi, I saw your ad. What is the price for 50 units?”', 'time' => '10:02', 'steps' => [['10:02', 'New lead created', 'WhatsApp'], ['10:02', 'Given to Aman, next in turn', 'auto'], ['10:02', 'Welcome reply sent', 'automation'], ['10:17', 'First call due in My day', 'reminder']]],
                        'src-web' => ['icon' => 'globe', 'from' => 'Website form · Get a free quote', 'body' => 'Rohit Mehta · Pune · “Need a quote for 200 units by Monday.”', 'time' => '11:40', 'steps' => [['11:40', 'New lead created', 'Website'], ['11:40', 'Given to Neha, next in turn', 'auto'], ['11:40', 'Neha alerted on her phone', 'push'], ['11:55', 'First call due in My day', 'reminder']]],
                        'src-fb' => ['icon' => 'megaphone', 'from' => 'Facebook lead form · Diwali offer', 'body' => 'Kavita Joshi · Joshi Builders · asked for a site visit', 'time' => '16:05', 'steps' => [['16:05', 'New lead created', 'Facebook'], ['16:05', 'Campaign saved for reports', 'Diwali offer'], ['16:05', 'Given to Aman, next in turn', 'auto'], ['16:20', 'First call due in My day', 'reminder']]],
                        'src-im' => ['icon' => 'building', 'from' => 'IndiaMART enquiry · Nashik', 'body' => 'Anil Kapoor · Steel storage racks · quantity 200', 'time' => '09:35', 'steps' => [['09:35', 'Pulled in automatically', 'every 5 min'], ['09:35', 'Product noted on the lead', 'racks'], ['09:35', 'Given to Neha, next in turn', 'auto'], ['10:15', 'First call due after opening', '10:00 start']]],
                        'src-csv' => ['icon' => 'file', 'from' => 'leads.csv · from Excel', 'body' => '248 rows: names, phone numbers, sources and notes', 'time' => '12:10', 'steps' => [['12:10', '246 leads imported', 'CSV'], ['12:10', '2 skipped: number already saved', 'no duplicates'], ['12:10', 'Shared between your agents', 'in turn'], ['12:10', 'In each agent’s list', 'done']]],
                    ] as $id => $demo)
                        <div class="l-demo-panel" data-src="{{ $id }}">
                            <div class="l-demo-in">
                                <div class="l-demo-in-head"><span class="l-demo-icon"><x-icon :name="$demo['icon']"/></span><strong>{{ $demo['from'] }}</strong><code>{{ $demo['time'] }}</code></div>
                                <p>{{ $demo['body'] }}</p>
                            </div>
                            <span class="l-demo-arrow" aria-hidden="true"><x-icon name="arrow-right"/></span>
                            <ol class="l-demo-out">
                                @foreach ($demo['steps'] as [$time, $what, $tag])
                                    <li><code>{{ $time }}</code><span><x-icon name="check" class="icon sm"/>{{ $what }}</span><em>{{ $tag }}</em></li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="l-sources" aria-label="Where leads come from">
        <div class="l-container">
            <p class="l-rule"><span>Leads arrive <strong>on their own</strong> from</span></p>
            <ul>
                <li><x-icon name="whatsapp"/>WhatsApp</li>
                <li><x-icon name="megaphone"/>Facebook &amp; Instagram</li>
                <li><x-icon name="search-ad"/>Google Ads</li>
                <li><x-icon name="building"/>IndiaMART</li>
                <li><x-icon name="globe"/>Your website</li>
                <li><x-icon name="file"/>Excel &amp; CSV</li>
                <li><x-icon name="code"/>API</li>
            </ul>
        </div>
    </section>

    <div class="l-rails">
        {{-- One list ------------------------------------------------------ --}}
        <section class="l-section" id="features">
            <div class="l-container">
                <div class="l-split">
                    <div class="l-split-text">
                        <h2>Every enquiry, <em>in one place.</em></h2>
                        <p>Website, WhatsApp, ads and spreadsheets land in one list. When someone enquires again, it joins their history instead of becoming a second lead for a second salesperson.</p>
                        <a href="#how" class="l-link">See how to get started <x-icon name="arrow-right" class="icon sm"/></a>
                    </div>
                    <div class="l-art violet">{!! $window('app-dashboard', '/dashboard', 'The Convera dashboard: follow-ups due today, speed to lead and where leads come from', false) !!}</div>
                </div>
                <div class="l-minis">
                    <div><h3>Shared in turn <x-icon name="arrow-right" class="icon sm"/></h3><p>Each new lead goes to the next agent the second it arrives.</p></div>
                    <div><h3>Reminders that work <x-icon name="arrow-right" class="icon sm"/></h3><p>In the app, on the phone and by email when a follow-up is due.</p></div>
                    <div><h3>One person, one lead <x-icon name="arrow-right" class="icon sm"/></h3><p>Repeat enquiries join the history, however the number is written.</p></div>
                    <div><h3>Works on any phone <x-icon name="arrow-right" class="icon sm"/></h3><p>Call, WhatsApp and log what happened in one tap.</p></div>
                </div>
            </div>
        </section>

        {{-- Speed to lead -------------------------------------------------- --}}
        <section class="l-section" id="speed">
            <div class="l-container">
                <div class="l-split">
                    <div class="l-split-text">
                        <h2>Reply first. <em>Win more.</em></h2>
                        <p>The first business to reply usually gets the order. Convera hands every new lead to someone at once, reminds them, and passes it on if nobody answers.</p>
                        <div class="l-stat">
                            <strong>5 min</strong>
                            <code>the reply target every new lead is measured against</code>
                        </div>
                    </div>
                    <div class="l-art blue">
                        <div class="l-timeline">
                            <div class="l-timeline-head"><x-icon name="user" class="icon sm"/><span>Kavita Joshi · Facebook ad</span><em class="ok">Contacted</em></div>
                            <ol>
                                <li><code>16:05</code><i class="blue"></i><span>New lead from the Diwali offer</span><em>Facebook</em></li>
                                <li><code>16:05</code><i class="blue"></i><span>Given to Aman, next in turn</span><em>auto</em></li>
                                <li><code>16:05</code><i class="blue"></i><span>WhatsApp welcome sent</span><em>automation</em></li>
                                <li><code>16:35</code><i class="amber"></i><span>No reply from Aman · passed to Neha</span><em>30-min rule</em></li>
                                <li><code>16:38</code><i class="green"></i><span>Neha called · interested</span><em>logged</em></li>
                            </ol>
                            <div class="l-timeline-foot"><x-icon name="check" class="icon sm"/><span>Next follow-up planned</span><code>Tomorrow 11:00</code></div>
                        </div>
                    </div>
                </div>
                <div class="l-cols">
                    <div><h3>Working hours, respected</h3><p>Leads that come in at night are due just after you open, so nobody starts the day already late.</p></div>
                    <div><h3>Passed on if nobody answers</h3><p>Choose how long a new lead may wait. After that it moves to the next agent and admins are told.</p></div>
                    <div><h3>An away message at night</h3><p>Outside working hours, people who write on WhatsApp get a friendly reply that you'll be back soon.</p></div>
                </div>
            </div>
        </section>

        {{-- Product tour ---------------------------------------------------- --}}
        <section class="l-section" id="product">
            <div class="l-container">
                <div class="l-heading">
                    <span class="l-label">One app for the whole team</span>
                    <h2>Everything your team needs. <em>Nothing it doesn’t.</em></h2>
                    <p>No bloated menus and no week-long setup. Every screen explains itself, so new staff are working leads on day one.</p>
                </div>
                <div class="l-tour">
                    @php($tour = [
                        ['inbox', 'app-inbox', '/inbox', 'WhatsApp inbox', 'Chat from one shared inbox. Every message sits next to the lead it belongs to.', 'The WhatsApp inbox with a conversation open next to the lead’s details'],
                        ['lead', 'app-lead', '/leads/60', 'One page per lead', 'Details, stage, every call and message, the score and the next step on one screen.', 'A lead page with the follow-up form, lead score and to-dos'],
                        ['today', 'app-today', '/today', 'My day', 'Each agent’s calls, meetings and to-dos for today, oldest first, with one-tap calling.', 'My day: overdue and today’s follow-ups for one agent'],
                        ['board', 'app-board', '/leads?view=board', 'Pipeline board', 'Drag a deal to its next stage and see what is in the pipeline at a glance.', 'The pipeline board with leads in New, Contacted, Not Reachable and Interested'],
                        ['reports', 'app-reports', '/reports', 'Reports', 'Who replies fastest, which sources win deals and why leads are lost.', 'Reports: new leads, answered in 5 minutes, wins and the funnel'],
                    ])
                    @foreach ($tour as $i => [$key])
                        <input type="radio" name="l-tour" id="tour-{{ $key }}" class="sr-only" @checked($i === 0)>
                    @endforeach
                    <div class="l-tour-list">
                        @foreach ($tour as [$key, , , $title, $text])
                            <label for="tour-{{ $key }}"><strong>{{ $title }}</strong><span>{{ $text }}</span></label>
                        @endforeach
                    </div>
                    <div class="l-tour-stage l-art indigo">
                        @foreach ($tour as [$key, $image, $path, , , $alt])
                            <div class="l-tour-shot" data-tour="{{ $key }}">{!! $window($image, $path, $alt) !!}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- Built for India (bento) --------------------------------------- --}}
        <section class="l-section" id="whatsapp">
            <div class="l-container">
                <div class="l-heading left">
                    <h2>Built for how India sells. <em>On WhatsApp, in Hinglish, on the phone.</em></h2>
                    <p>The tools Indian sales teams actually use, inside one CRM.</p>
                </div>
                <div class="l-bento">
                    <article>
                        <h3>WhatsApp, the official way</h3>
                        <p>Meta’s official WhatsApp Cloud API with your own business number. Two-way chat, read receipts and approved templates, without putting your number at risk with unofficial tools.</p>
                        <div class="l-mock l-chat">
                            <div class="in">Is the 3BHK in Baner still available?<code>18:04</code></div>
                            <div class="out">Yes! Would you like a site visit this Saturday at 11?<code>18:06 ✓✓</code></div>
                            <div class="in">Saturday works 👍<code>18:07</code></div>
                            <div class="l-mock-foot"><span><x-icon name="check" class="icon sm"/>Reply window open</span><span>Example chat</span></div>
                        </div>
                    </article>
                    <article class="soft" id="voice-notes">
                        <h3>Voice notes, written down</h3>
                        <p>When a lead sends a voice note, Convera writes down what they said in Hindi, Hinglish or English, with a one-line summary.</p>
                        <div class="l-mock l-voice">
                            <div class="l-wave"><x-icon name="phone" class="icon sm"/><span>@for ($b = 0; $b < 28; $b++)<i style="height: {{ [6, 12, 18, 9, 22, 14, 8, 16, 24, 11, 7, 19, 13, 21, 10, 15, 6, 18, 23, 12, 9, 16, 20, 8, 14, 11, 17, 7][$b] }}px"></i>@endfor</span><code>0:14</code></div>
                            <blockquote>“Bhaiya, 50 piece ka rate bhej do, Monday tak delivery chahiye.”</blockquote>
                            <div class="l-summary"><x-icon name="sparkles" class="icon sm"/>Wants a price for 50 pieces, delivered by Monday.</div>
                            <div class="l-mock-foot"><span>Hinglish → written</span><span>Example</span></div>
                        </div>
                    </article>
                    <article class="soft">
                        <h3>IndiaMART, built in</h3>
                        <p>Paste your IndiaMART CRM key once. Buyer enquiries arrive every 5 minutes with the product they asked about.</p>
                        <div class="l-mock l-rows">
                            <div><x-icon name="building" class="icon sm"/><span>Steel storage racks · Nashik</span><code>2 min ago</code></div>
                            <div><x-icon name="building" class="icon sm"/><span>Industrial shelving · Pune</span><code>7 min ago</code></div>
                            <div><x-icon name="building" class="icon sm"/><span>Slotted angle racks · Surat</span><code>12 min ago</code></div>
                            <div class="l-mock-foot"><span><x-icon name="check" class="icon sm"/>Assigned automatically</span><span>Example enquiries</span></div>
                        </div>
                    </article>
                    <article>
                        <h3>An assistant that knows the lead</h3>
                        <p>One click summarises the conversation, rates the lead hot, warm or cold and drafts the next WhatsApp message in the lead’s own language.</p>
                        <div class="l-mock l-ai">
                            <div class="l-ai-head"><span class="hot">Hot</span><span>Asked for pricing and a demo this week</span></div>
                            <div class="l-ai-next"><code>Next step</code>Send the price for 50 units and offer two demo slots.</div>
                            <div class="l-ai-msg">Hi Pooja! 50 units are ₹1,20,000 with delivery. Would tomorrow 11am or 4pm suit you for a quick demo?</div>
                            <div class="l-mock-foot"><span><x-icon name="sparkles" class="icon sm"/>Ready to send</span><span>Example</span></div>
                        </div>
                    </article>
                    <article>
                        <h3>Reports that show what works</h3>
                        <p>See which sources and campaigns bring deals, not just leads, and how fast each person replies.</p>
                        <div class="l-mock l-bars">
                            @foreach ([['Referral', 32], ['Website form', 24], ['Facebook ads', 18], ['IndiaMART', 14], ['Walk-in', 9]] as [$source, $rate])
                                <div><span>{{ $source }}</span><b style="--w: {{ $rate * 2.6 }}%"></b><code>{{ $rate }}%</code></div>
                            @endforeach
                            <div class="l-mock-foot"><span>Win rate by source</span><span>Example numbers</span></div>
                        </div>
                    </article>
                    <article class="soft">
                        <h3>Customer data, handled with care</h3>
                        <p>Consent is recorded, a STOP reply is honoured on its own, and a person’s data can be exported or erased on request, as India’s DPDP Act expects.</p>
                        <div class="l-mock l-rows">
                            <div><x-icon name="shield" class="icon sm"/><span>Consent recorded · Website form</span><code>3 Oct</code></div>
                            <div><x-icon name="x" class="icon sm"/><span>Replied STOP · messages stopped</span><code>auto</code></div>
                            <div><x-icon name="download" class="icon sm"/><span>Data exported on request</span><code>.json</code></div>
                            <div class="l-mock-foot"><span><x-icon name="check" class="icon sm"/>Every step logged</span><span>Example</span></div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        {{-- How it works ---------------------------------------------------- --}}
        <section class="l-section" id="how">
            <div class="l-container l-steps-wrap">
                <div class="l-steps-intro">
                    <span class="l-label">Up and running today</span>
                    <h2>From sign-up to first follow-up <em>in three steps.</em></h2>
                    <a href="{{ route('register') }}" class="l-btn light">Start your free trial</a>
                </div>
                <ol class="l-steps">
                    <li><code>01</code><h3>Create your workspace</h3><p>Sign up in a minute and invite your team, with a password or a link you can send on WhatsApp. Your pipeline and sources come ready to use.</p></li>
                    <li><code>02</code><h3>Connect where leads come from</h3><p>Share your lead form, connect WhatsApp, your ad accounts and IndiaMART, or import the spreadsheet you already have.</p></li>
                    <li><code>03</code><h3>Follow up and close</h3><p>Leads are shared out on their own, reminders keep everyone on time, and reports show what is working.</p></li>
                </ol>
            </div>
        </section>
    </div>

    {{-- Pricing (dark) ------------------------------------------------------ --}}
    <section class="l-dark" id="pricing">
        <div class="l-container">
            <div class="l-heading">
                <span class="l-label invert">Pricing</span>
                <h2>Simple plans. <em>No surprises.</em></h2>
                <p>Every plan starts with a {{ $trialDays }}-day free trial of everything. Prices in rupees per month, excluding GST.</p>
            </div>
            <div class="l-plans">
                @foreach ($plans as $plan)
                    @php($recommended = $plan->key === 'growth')
                    <article @class(['l-plan', 'featured' => $recommended])>
                        <div class="l-plan-head">
                            <h3>{{ $plan->name }}</h3>
                            @if ($recommended)<span class="l-plan-tag">Recommended</span>@endif
                        </div>
                        <div class="l-plan-users">Up to {{ $plan->maxUsers }} users</div>
                        <div class="l-price">₹{{ number_format($plan->price) }}<span>/ month</span></div>
                        <ul>
                            @foreach (config('plans.included') as $item)
                                <li><x-icon name="check" class="icon sm"/>{{ $item }}</li>
                            @endforeach
                            @foreach ($plan->features as $feature)
                                <li class="extra"><x-icon name="check" class="icon sm"/>{{ $feature->label() }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('register') }}" @class(['l-btn', 'white' => $recommended, 'ghost-dark' => ! $recommended])>Start free trial</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --------------------------------------------------------------- --}}
    <section class="l-section" id="faq">
        <div class="l-container l-faq-wrap">
            <div class="l-faq-intro">
                <span class="l-label">FAQ</span>
                <h2>Questions, <em>answered.</em></h2>
                @if (config('crm.support_email'))
                    <p>Anything else? Email <a href="mailto:{{ config('crm.support_email') }}">{{ config('crm.support_email') }}</a> and we'll help you out.</p>
                @endif
            </div>
            <div class="l-faq">
                <details><summary>Do I need a credit card to try it?</summary><p>No. You get {{ $trialDays }} days with every feature. Choose a plan whenever you're ready; your leads and settings stay exactly as they are.</p></details>
                <details><summary>Is the WhatsApp integration official?</summary><p>Yes. It uses Meta's official WhatsApp Cloud API with your own business number, so there's no risk of the number being banned for using unofficial tools. Meta's own message charges apply.</p></details>
                <details><summary>Can I bring my existing leads?</summary><p>Yes. Import a CSV exported from Excel or Google Sheets. Numbers already in Convera are skipped, and you can export everything again at any time.</p></details>
                <details><summary>Who can see our leads?</summary><p>Only your team. Every workspace is completely separate, agents see only the leads assigned to them, and connection passwords for WhatsApp and ads are stored encrypted.</p></details>
                <details><summary>Does it work on mobile?</summary><p>Yes. It works in any phone browser and can be added to your home screen like an app, with one-tap call, WhatsApp and follow-up logging.</p></details>
                <details><summary>Can I change or cancel my plan?</summary><p>
                    Yes, any time{{ $paymentsEnabled ? ', from Settings → Billing' : '' }}. If you cancel, you keep access until the end of the period you've paid for.
                    @if (! $paymentsEnabled)
                        To choose or change a plan, {{ config('crm.support_email') ? 'email '.config('crm.support_email') : 'contact us' }} and we'll switch it for you.
                    @endif
                </p></details>
            </div>
        </div>
    </section>

    {{-- Final call to action ------------------------------------------------- --}}
    <section class="l-final">
        <div class="l-container">
            <div class="l-final-card">
                <h2>Start following up on every lead today.</h2>
                <p>Set up in minutes. Free for {{ $trialDays }} days, no credit card.</p>
                <div class="l-cta">
                    <a href="{{ route('register') }}" class="l-btn white">Create your workspace</a>
                    <a href="{{ route('login') }}" class="l-btn glass">Log in</a>
                </div>
            </div>
        </div>
    </section>
</main>

@include('partials.public-footer')
<script src="{{ \App\Support\Asset::url('js/app.js') }}" defer></script>
</body>
</html>
