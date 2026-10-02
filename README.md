# CRM (multi-tenant SaaS)

A lead-management CRM sold as a service to small sales teams. A company
signs up and gets a private workspace with:
- leads from every channel (website, WhatsApp, Facebook/Instagram and Google
  lead ads, CSV, API)
- automatic assignment to agents
- follow-ups and reminders
- WhatsApp conversations inside the CRM
- automations, reports and an AI assistant

Built with Laravel 13 (PHP 8.3+), MySQL, server-rendered Blade pages and one
CSS file. There is no JavaScript build step. It runs on any PHP host,
including shared hosting.

## Why it looks the way it does

Market research (October 2026):
- **Price.** Indian SMB CRMs cost about ₹400–800 per user per month (Bigin,
  TeleCRM, Zoho, Kylas).
- **What buyers want.** The features that sell are WhatsApp, fast lead
  capture from ads, and speed-to-lead.
- **What they dislike most.** Cluttered, complex screens.

So the sidebar keeps the daily work in one short list (Dashboard, Leads,
Inbox, Follow-ups, Reports) and puts Settings under Admin. Search and the
New lead button sit in the top bar on every page. Settings are grouped
(Workspace, Sales process, Connections, Security), and each page does one
job.

The look is "Ink & Indigo": a dark ink sidebar, calm light surfaces, one
indigo accent for actions, and colour only where it means something (coral
for overdue, amber for due today, green for won). Headings use Bricolage
Grotesque and text uses Instrument Sans. It lives in `public/css/app.css`,
organized as tokens, base, shell, components, feature pieces and responsive
rules. Shared Blade components cover icons, avatars and empty states. The
landing page adds `public/css/landing.css` on top.

**Branding.** The product name comes from `APP_NAME`. The icon is in
`public/icons/` (regenerate it from your logo). The landing screenshots are
in `public/images/app-*.webp`.

## Features

| Area | What it does |
|---|---|
| Accounts | Self-service sign-up, password reset, a 14-day trial with every feature, and rate-limited login. Email verification can be switched on with `CRM_REQUIRE_EMAIL_VERIFICATION=true` once email works. |
| Roles | **Admin** sees everything. **Agent** sees only their own leads. |
| Leads | A list or a board. The list has search, filters and bulk status change, reassign or delete. The board has one column per stage with its count and deal value, and you drag a card to move it. The same phone number can't be added twice in a workspace. Custom fields. |
| Pipeline | Views for Fresh, In progress, Follow-ups due, Dormant, Won and Lost. On a lead, click a stage in the stage bar (or Mark won) to move it, or log a follow-up with outcome chips and quick dates. Every move is kept in the lead's history. |
| Assignment | Routing rules send matching leads (by source, city or any custom field) to a group of people who take turns. Everything else goes round-robin across agents. People marked away, and anyone over an optional open-lead limit, are skipped, but a lead is never left without an owner (`app/Routing`). |
| Lead capture | Hosted website form (link or iframe), Developer API, Facebook & Instagram lead ads, Google Ads lead forms, WhatsApp, and CSV import. A repeat enquiry is added to the existing lead instead of being lost. |
| WhatsApp | Official Cloud API: two-way chat on the lead, an inbox with unread counts, approved templates outside the 24-hour window, and sent/delivered/read ticks. |
| Lead scoring | Every open lead gets a score from 0 to 100 (hot, warm, cold). It's built from recent replies, follow-ups, stage, deal value, how well its source converts, priority and the AI's rating. The list can sort by it, the board shows it, and the lead page explains each point. It updates as things happen and hourly (`app/Scoring`, one class per signal). |
| Sequences | Timed follow-ups that run by themselves, for example "day 0: welcome template, day 2: remind the owner to call, day 7: offer". A sequence stops when the lead replies (optional) or is won or lost, and only runs inside working hours. Start one from a lead, from the list (bulk) or from an automation (`app/Sequences`: one handler class per step type). |
| Autopilot | Routine work with one switch each (Settings → Autopilot). It plans the first call for new leads, passes on leads nobody answered in time, plans the next follow-up when no date is picked, and moves leads to Contacted after the first WhatsApp message. It also reopens lost leads that come back, nudges quiet leads, can close dead ones, sends an away message outside working hours and lets AI flag hot leads. When someone leaves, their leads are handed over. Everyone gets a 9:00 morning summary and admins a Monday report. Each step is written in the lead's history. |
| Automations | "When a new lead arrives / status changes, if source/status is X, then assign, set status, send a WhatsApp template, schedule a follow-up, notify someone." Rules never trigger each other, so they can't loop. |
| Notifications | In-app bell for new assignments, incoming WhatsApp messages and automation alerts. Follow-up reminders also go by email. |
| Reports | Date ranges, the New → Contacted → Won funnel, average time to first contact, win rate, won value, new leads per day/week, and per-source and per-agent tables. |
| AI assistant | One click returns a summary, hot/warm/cold score, next step and a ready-to-send WhatsApp reply in the lead's own language. Runs on Google Gemini by default (`AI_PROVIDER=gemini`) or on Claude (`AI_PROVIDER=anthropic`), with a monthly allowance per workspace. Both return the same JSON shape. |
| Billing | Starter, Growth and Pro plans with user limits and feature gates. Paid through Razorpay subscriptions on the hosted payment page. |
| Owner panel | `/platform` for you, the SaaS operator: all workspaces, revenue, suspend or reactivate, extend trials, record offline payments, and a **System health** panel. |
| Security | Optional two-factor login (authenticator apps, recovery codes, replay protection), which a workspace can require. You can see signed-in devices and sign the others out, and a password change signs out other sessions. A strict Content Security Policy only lets the app's own scripts run (a fresh nonce per request), and frame and HSTS headers are sent. Login, 2FA, password reset, the API, forms and webhooks are rate-limited. Live passwords need 10+ characters with letters and numbers and are checked against known breaches. Integration credentials and 2FA secrets are encrypted at rest. |
| Team | Invite teammates by email (single-use link, 7-day expiry, counted against seats), or add them with a password. |
| Activity log | Who did what and when: sign-ins, lead changes, team and settings changes, imports and exports, security events. Kept 12 months. |
| Data rights | One-click export of all workspace data (ZIP of CSVs), permanent workspace deletion, and draft Privacy Policy and Terms pages (DPDP-oriented). |
| Time zones | Each workspace has a time zone. Times are stored in UTC and entered and shown in local time, so reminders fire at the right local time. |
| Emails | Welcome email, plus trial reminders 3 days and 1 day before the trial ends and when it ends. |
| Push | Phone and desktop notifications (Web Push) for new leads, due follow-ups, WhatsApp messages and automation alerts. |
| Help | A searchable in-app help page. |
| Mobile | Installable app (manifest + service worker), phone-friendly layouts with a slide-out menu, click-to-call, offline page. |
| Landing page | `/` for visitors: hero, lead sources, features, WhatsApp, how it works, pricing (read from `config/plans.php`), FAQ. Signed-in users go straight to the dashboard. |

### Plans (`config/plans.php`)

| Plan | ₹/month | Users | Adds |
|---|---|---|---|
| Free trial (14 days) | 0 | 5 | everything |
| Starter | 999 | 3 | core CRM |
| Growth | 2,499 | 10 | WhatsApp, lead ads, automations |
| Pro | 4,999 | 25 | + AI assistant |

## Run it locally

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # or point DB_* at MySQL
php artisan migrate --seed            # demo workspace with sample data
php artisan serve --port=8080
```

Log in at http://localhost:8080 as `admin@demo.test` / `password`. The demo
agents are `asha@demo.test` and `ravi@demo.test`. Add
`PLATFORM_ADMIN_EMAILS=admin@demo.test` to `.env` to see the owner panel.

The demo WhatsApp connection uses placeholder credentials, so the inbox
has something to show. Enter real ones under **Settings → Integrations** to
send messages.

Before every commit, run `composer check`. It runs three things, and the
same checks run in CI on every push:

- code style with Pint (`vendor/bin/pint` fixes it);
- static analysis with Larastan at level 5 (`composer analyse`);
- the tests (`php artisan test`, 170 tests, passing on SQLite and MySQL).

## Architecture

```
HTTP request
  └─ Middleware   auth · verified · active user · subscribed (plan live) · feature:x · can:admin · rate limits
      └─ FormRequest   validation; every id must belong to the user's organization
          └─ Controller   thin: call a service, return a view or redirect
              └─ Service   business rules
                  └─ Model + tenant scope  →  database
Model saves ──► LeadObserver ──► domain events (LeadCreated, LeadStatusChanged, LeadAssigned)
                                   ├─ NotifyAssignee
                                   └─ RunAutomations
```

```
app/
  Ai/            LeadAssistant, AiProvider, InsightGenerator (interface) with
                 GeminiInsightGenerator and ClaudeInsightGenerator, LeadInsight
  Billing/       Plan, PlanCatalog, RazorpayGateway, SubscriptionManager
  Integrations/  MetaGraph (WhatsApp + Lead Ads client), WhatsAppService, FacebookLeadAds
  Services/      LeadService, LeadIntake, LeadAssigner, AutomationRunner, ReportService,
                 DashboardStats, LeadImporter/Exporter, OrganizationRegistrar, ApiKeyManager
  Tenancy/       TenantContext, OrganizationScope, BelongsToOrganization
  Events/ Listeners/ Observers/ Notifications/ Jobs/ Console/Commands/
  Http/          Controllers (Auth, Settings, Platform, Webhooks, Api), Requests, Middleware
  Enums/ Models/ Support/ View/Composers/
```

Design rules:
- **Dependencies point one way.** Controllers depend on services, and
  services depend on models. Nothing depends on a controller.
- **One way in for leads.** All leads come in through `LeadService` /
  `LeadIntake`, so the UI, import, API, form, WhatsApp and ads behave the same.
- **External APIs sit behind small adapters.** `MetaGraph`,
  `RazorpayGateway` and `InsightGenerator` each wrap one service, and tests
  swap in fakes.
- **Webhooks are checked and de-duplicated.** Each one verifies its
  signature or shared key. `webhook_events` turns retried deliveries into
  no-ops.

### Multi-tenancy

All companies share one database, and every business table has an
`organization_id` column.
- **Scoping.** The `BelongsToOrganization` trait filters every query to the
  current organization and stamps new rows with it.
- **Who the current organization is.** `TenantContext` takes it from the
  signed-in user, or it's set explicitly by an API key, a webhook or a
  queued job.
- **Second checks.** Policies check the organization again, and validation
  rules are limited to the organization.
- **Other tenants' data.** Records from another tenant return 404.

`tests/Feature/TenantIsolationTest.php` tries to cross tenants through every
route. **When you add a business table, give it `organization_id` and the
trait, and add a case to that test.**

## Background work

Add one cron entry:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

- **Follow-up reminders** (`crm:send-reminders`) run every 5 minutes.
- **Trial emails** (`crm:trial-reminders`) run daily at 10:00 India time.
- **Autopilot** (`crm:autopilot`) runs every 5 minutes, only inside each
  workspace's working hours.
- **Morning summaries and Monday reports** (`crm:digests`) go out at 9:00 in
  each workspace's own time zone.
- **WhatsApp templates** (`crm:sync-templates`) sync every night.
- **Sequences** (`crm:sequences`) send due steps every 5 minutes, inside working hours.
- **Lead scores** (`crm:score-leads`) refresh hourly.
- **Encrypted database backups** run daily, with cleanup and monitoring
  (`backup:run --only-db`, `backup:clean`, `backup:monitor`).
- **Cleanup:** old activity-log entries and failed jobs are pruned daily.
- **Heartbeat:** a check-in every minute for the System health panel.
  Run `php artisan crm:health` on the server any time.
- **Queued jobs** (WhatsApp sends, Facebook lead fetches) are processed by
  the scheduler every minute. If you run a permanent `php artisan
  queue:work` instead, set `CRM_SCHEDULER_RUNS_QUEUE=false`.

## Configuration (`.env`)

| Variable | Purpose |
|---|---|
| `APP_NAME` | Product name shown everywhere, including the landing page |
| `PLATFORM_ADMIN_EMAILS` | Comma-separated emails that can open `/platform` |
| `CRM_SUPPORT_EMAIL` | Contact address shown in the landing page footer |
| `MAIL_*` | Password reset, reminder and (optional) verification emails |
| `CRM_REQUIRE_EMAIL_VERIFICATION` | `true` to make new sign-ups confirm their email (default `false`; needs working `MAIL_*`) |
| `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` | Payments. Webhook URL: `https://your-domain/api/webhooks/razorpay`; subscribe to `subscription.*` events |
| `RAZORPAY_PLAN_STARTER` / `_GROWTH` / `_PRO` | Plan ids created once in the Razorpay dashboard (monthly) |
| `CRM_DEFAULT_TIMEZONE` | Time zone for new workspaces (default `Asia/Kolkata`) |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | Push notifications. Generate them once with `php artisan crm:vapid-keys` |
| `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_DISKS`, `BACKUP_NOTIFY_EMAIL` | Encrypted backups, kept locally and off-site (`backups,s3`) |
| `SENTRY_LARAVEL_DSN` | Error tracking |
| `CRM_FORCE_HTTPS`, `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE` | Production HTTPS behind a proxy or load balancer |
| `AI_PROVIDER` + `GEMINI_API_KEY` | Turns on the AI assistant with Google Gemini (`GEMINI_MODEL` defaults to `gemini-3.8-flash`). Get a key at https://aistudio.google.com/apikey |
| `ANTHROPIC_API_KEY` | Only with `AI_PROVIDER=anthropic` (`ANTHROPIC_MODEL` defaults to `claude-opus-5-5`) |
| `META_GRAPH_VERSION` | Graph API version for WhatsApp and Lead Ads (default `v25.0`) |
| `CRM_TRIAL_DAYS`, `CRM_AI_MONTHLY_LIMIT`, `CRM_DORMANT_AFTER_DAYS`, `CRM_IMPORT_MAX_ROWS` | Product limits |

Customers connect their own WhatsApp number, Facebook page and Google Ads
under **Settings → Integrations**. Each connection gets its own webhook URL
and secret, and the setup steps are shown on that page. Their credentials
are stored encrypted.

## Deploying to production

Follow **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**: an Ubuntu server, MySQL,
nginx with free HTTPS, background workers, the cron entry, backups and
monitoring. All the config files are in `deploy/`. The production settings
template is `.env.production.example`. To update later, run
`./deploy/deploy.sh`.

GitHub Actions (`.github/workflows/ci.yml`) runs code style, a dependency
vulnerability audit and the full test suite on SQLite and MySQL for every
push.

## What's next

- **Payments:** switch on Razorpay (the code is in place). Add your keys and
  plan IDs, a refund policy, and GST invoices.
- **Legal review:** have a lawyer review and complete `resources/markdown/privacy.md`
  and `terms.md` (replace every `[bracketed]` item).
- **WhatsApp one-click onboarding:** register as a Meta Tech Provider for
  Embedded Signup.
- **More channels:** IndiaMART, JustDial and 99acres lead sync; click-to-call
  with call recording.
