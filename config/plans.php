<?php

/*
| Pricing, modelled on the Indian SMB CRM market (Rs 400-800 per user per
| month): flat monthly plans with a user limit are simpler to buy and to
| bill than per-seat pricing. Amounts are in rupees, before GST.
|
| "razorpay_plan_id" is the Plan created once in the Razorpay dashboard.
*/

return [

    'trial_days' => (int) env('CRM_TRIAL_DAYS', 14),

    'plans' => [
        'trial' => [
            'name' => 'Free trial',
            'price' => 0,
            'max_users' => 5,
            'features' => ['whatsapp', 'lead_ads', 'automations', 'ai'],
            'razorpay_plan_id' => null,
        ],
        'starter' => [
            'name' => 'Starter',
            'price' => 999,
            'max_users' => 3,
            'features' => [],
            'razorpay_plan_id' => env('RAZORPAY_PLAN_STARTER'),
        ],
        'growth' => [
            'name' => 'Growth',
            'price' => 2499,
            'max_users' => 10,
            'features' => ['whatsapp', 'lead_ads', 'automations'],
            'razorpay_plan_id' => env('RAZORPAY_PLAN_GROWTH'),
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 4999,
            'max_users' => 25,
            'features' => ['whatsapp', 'lead_ads', 'automations', 'ai'],
            'razorpay_plan_id' => env('RAZORPAY_PLAN_PRO'),
        ],
    ],

    // What every plan includes, shown on the billing page.
    'included' => [
        'Leads, pipeline, follow-ups and reminders',
        'Website form, API, CSV import and export',
        'Custom fields, bulk actions and reports',
    ],

];
