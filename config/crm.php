<?php

return [

    /*
    | An open lead with no logged follow-up for this many days is "dormant".
    */
    'dormant_after_days' => (int) env('CRM_DORMANT_AFTER_DAYS', 30),

    /*
    | Make new sign-ups confirm their email before using the app. Needs
    | working MAIL_* settings, so it is off until email is set up.
    */
    'require_email_verification' => (bool) env('CRM_REQUIRE_EMAIL_VERIFICATION', false),

    /*
    | Upper bound on rows accepted by a single CSV import.
    */
    'import_max_rows' => (int) env('CRM_IMPORT_MAX_ROWS', 5000),

    /*
    | Let the scheduler drain the job queue every minute (for shared hosting
    | where no queue worker can run permanently).
    */
    'scheduler_runs_queue' => (bool) env('CRM_SCHEDULER_RUNS_QUEUE', true),

    /*
    | AI lead analyses each workspace may run per calendar month (cost cap).
    */
    'ai_monthly_limit' => (int) env('CRM_AI_MONTHLY_LIMIT', 500),

    /*
    | Pipeline every new organization starts with. Admins can edit it later.
    */
    'default_statuses' => [
        ['name' => 'New', 'type' => 'open', 'color' => '#3b82f6'],
        ['name' => 'Contacted', 'type' => 'open', 'color' => '#8b5cf6'],
        ['name' => 'Not Reachable', 'type' => 'open', 'color' => '#f59e0b'],
        ['name' => 'Interested', 'type' => 'open', 'color' => '#06b6d4'],
        ['name' => 'Meeting Done', 'type' => 'open', 'color' => '#14b8a6'],
        ['name' => 'Won', 'type' => 'won', 'color' => '#16a34a'],
        ['name' => 'Lost', 'type' => 'lost', 'color' => '#dc2626'],
    ],

    'default_sources' => ['Website', 'Phone Call', 'Walk-in', 'Referral', 'Social Media'],

    /*
    | Emails of the people who run this SaaS. They see the Platform panel
    | (all workspaces, suspend, extend trial, record offline payments).
    */
    /*
    | Shown in the landing page footer when set.
    */
    'support_email' => env('CRM_SUPPORT_EMAIL'),

    'platform_admins' => array_values(array_filter(array_map(
        'trim',
        explode(',', strtolower((string) env('PLATFORM_ADMIN_EMAILS', ''))),
    ))),

];
