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

So the app keeps five items in the sidebar (Dashboard, Leads, Inbox,
Reports, Settings). Every admin screen lives under Settings, and each page
does one job.

The design system lives in `public/css/app.css`, organized as tokens, base,
shell, components, feature pieces and responsive rules. Shared Blade
components cover icons, avatars and empty states. The landing page adds
`public/css/landing.css` on top.

**Branding.** The product name comes from `APP_NAME`. The icon is in
`public/icons/` (regenerate it from your logo). The landing screenshots are
in `public/images/app-*.webp`.

## Features

| Area | What it does |
|---|---|
| Accounts | Self-service sign-up, password reset, a 14-day trial with every feature, and rate-limited login. Email verification can be switched on with `CRM_REQUIRE_EMAIL_VERIFICATION=true` once email works. |
| Roles | **Admin** sees everything. **Agent** sees only their own leads. |
| Leads | Search and filters, the same phone number blocked twice in a workspace, custom fields, and bulk status change, reassign or delete. |
| Pipeline | Views for Fresh, In progress, Follow-ups due, Dormant, Won and Lost. One-tap outcome chips and quick follow-up dates. Full history on each lead. |
| Assignment | Round-robin across active agents, or by automation rules. |
| Lead capture | Hosted website form (link or iframe), Developer API, Facebook & Instagram lead ads, Google Ads lead forms, WhatsApp, and CSV import. A repeat enquiry is added to the existing lead instead of being lost. |
| WhatsApp | Official Cloud API: two-way chat on the lead, an inbox with unread counts, approved templates outside the 24-hour window, and sent/delivered/read ticks. |
| Automations | "When a new lead arrives / status changes, if source/status is X, then assign, set status, send a WhatsApp template, schedule a follow-up, notify someone." Rules never trigger each other, so they can't loop. |
| Notifications | In-app bell for new assignments, incoming WhatsApp messages and automation alerts. Follow-up reminders also go by email. |
| Reports | Date ranges, the New → Contacted → Won funnel, average time to first contact, win rate, won value, new leads per day/week, and per-source and per-agent tables. |
| AI assistant | One click returns a summary, hot/warm/cold score, next step and a ready-to-send WhatsApp reply in the lead's own language. Uses Claude via Anthropic's official PHP SDK, with a monthly allowance per workspace. |
| Billing | Starter, Growth and Pro plans with user limits and feature gates. Paid through Razorpay subscriptions on the hosted payment page. |
| Owner panel | `/platform` for you, the SaaS operator: all workspaces, revenue, suspend or reactivate, extend trials, record offline payments. |
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

To run the tests: `php artisan test` (107 tests).

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
  Ai/            LeadAssistant, InsightGenerator (interface), ClaudeInsightGenerator, LeadInsight
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
| `ANTHROPIC_API_KEY` | Turns on the AI assistant (`ANTHROPIC_MODEL` defaults to `claude-opus-5-5`) |
| `META_GRAPH_VERSION` | Graph API version for WhatsApp and Lead Ads (default `v25.0`) |
| `CRM_TRIAL_DAYS`, `CRM_AI_MONTHLY_LIMIT`, `CRM_DORMANT_AFTER_DAYS`, `CRM_IMPORT_MAX_ROWS` | Product limits |

Customers connect their own WhatsApp number, Facebook page and Google Ads
under **Settings → Integrations**. Each connection gets its own webhook URL
and secret, and the setup steps are shown on that page. Their credentials
are stored encrypted.

## Deploying to production

1. PHP 8.3+ with `pdo_mysql` and `gd`, plus MySQL 8+. Point the document
   root at `public/`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, an `https` `APP_URL`, and
   the database, mail, Razorpay and Anthropic values.
3. Run `composer install --no-dev --optimize-autoloader && php artisan key:generate && php artisan migrate --force && php artisan optimize`.
4. Add the cron entry above, serve over HTTPS only, and back up the
   database daily.

## What's next

- **WhatsApp one-click onboarding.** Register as a Meta Tech Provider so
  customers connect WhatsApp with Embedded Signup instead of pasting
  tokens.
- **More channels.** IndiaMART, JustDial and 99acres lead sync are common
  asks in India.
- **Calling.** Click-to-call through a telephony provider, with call
  recording on the lead timeline.
