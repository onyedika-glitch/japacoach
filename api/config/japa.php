<?php

return [

    // Where users are sent back to after Google sign-in and Bachs checkout.
    'frontend_url' => env('FRONTEND_URL', env('APP_URL')),

    // Google Play in-app products (one-time, consumable) and the plan each one grants.
    'google_play_products' => [
        'pass_weekly' => 'weekly',
        'pass_monthly' => 'monthly',
        'pass_sprint' => 'sprint',
    ],

    'mobile' => [
        // Extra Google OAuth client IDs whose ID tokens the mobile app may send (the web client ID is always accepted).
        'google_client_ids' => array_values(array_filter(explode(',', (string) env('GOOGLE_MOBILE_CLIENT_IDS', '')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Bachs checkouts are one-off payments, so each plan buys a fixed number of
    | days of premium access. Buying again before expiry stacks the days.
    |
    */

    'currency' => env('BACHS_CURRENCY', 'NGN'),

    'plans' => [
        'weekly' => [
            'name' => 'Weekly',
            'price' => (float) env('PLAN_WEEKLY_PRICE', 1500),
            'days' => 7,
            'highlight' => false,
            'features' => [
                '3 full speaking mock tests',
                'Unlimited speaking drills',
                '5 writing evaluations',
                'Unlimited reading & listening tests',
            ],
        ],
        'monthly' => [
            'name' => 'Monthly',
            'price' => (float) env('PLAN_MONTHLY_PRICE', 4000),
            'days' => 30,
            'highlight' => true,
            'features' => [
                '10 full speaking mock tests',
                'Unlimited speaking drills',
                '20 writing evaluations',
                'Unlimited reading & listening tests',
                'Full mock exams & study plan',
            ],
        ],
        'sprint' => [
            'name' => 'Exam Sprint (90 days)',
            'price' => (float) env('PLAN_SPRINT_PRICE', 10000),
            'days' => 90,
            'highlight' => false,
            'features' => [
                '10 full speaking mock tests every 30 days',
                'Unlimited speaking drills',
                '20 writing evaluations every 30 days',
                'Unlimited reading & listening tests',
                'Full mock exams & study plan',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage limits (rolling 30 days, null = unlimited)
    |--------------------------------------------------------------------------
    |
    | Limits protect the AI and transcription costs of each paying user.
    | "Unlimited" drills still have a fair-use ceiling.
    |
    */

    'limits' => [
        'free' => [
            'speaking_mocks' => 1,
            'speaking_drills' => 3,
            'writing' => 1,
            'reading' => 1,
            'listening' => 1,
        ],
        'weekly' => [
            'speaking_mocks' => 3,
            'speaking_drills' => 60,
            'writing' => 5,
            'reading' => null,
            'listening' => null,
        ],
        'monthly' => [
            'speaking_mocks' => 10,
            'speaking_drills' => 150,
            'writing' => 20,
            'reading' => null,
            'listening' => null,
        ],
        'sprint' => [
            'speaking_mocks' => 10,
            'speaking_drills' => 150,
            'writing' => 20,
            'reading' => null,
            'listening' => null,
        ],
    ],

    'referrals' => [
        // Extra full speaking mocks for the referrer when a friend makes their first payment.
        'referrer_bonus_mocks' => 2,
        // Extra full speaking mock for a new user who signed up with a referral code.
        'referee_bonus_mocks' => 1,
    ],

    'speaking' => [
        'max_answer_seconds' => 150,
        'max_upload_kb' => 15360,
        // Raw answer audio is deleted after this many days.
        'audio_retention_days' => 30,
    ],

    'voices' => [
        // Kokoro voice ids used for the examiner and listening recordings.
        'examiner' => env('KOKORO_EXAMINER_VOICE', 'bf_emma'),
        'listening' => ['bf_emma', 'bm_george', 'af_heart', 'am_michael', 'bf_isabella', 'bm_lewis'],
    ],
    /*
    |--------------------------------------------------------------------------
    | Search engines
    |--------------------------------------------------------------------------
    |
    | Public pages listed in /sitemap.xml. Update "updated" when a page's content
    | changes so Google re-crawls it; everything behind login stays out.
    |
    */

    // Who we are, for llms.txt, ai.txt and markdown pages. Keep in sync with the Organization JSON-LD in frontend/index.html.
    'site' => [
        'name' => 'Japa Coach',
        'description' => 'Japa Coach is an AI IELTS practice platform built in Nigeria for people preparing to study, work or settle abroad. Candidates answer speaking questions out loud, write timed essays and take full reading and listening tests, then get an estimated band and specific corrections in minutes.',
        'facts' => 'Covers all four IELTS modules for Academic and General Training, plus full mock exams and a study plan. Free to start; paid one-off passes from ₦1,500 a week with no automatic renewal. Web app at japacoach.com and an Android app. Independent: not affiliated with IELTS, the British Council, IDP or Cambridge. Band scores are estimates.',
        'contact_email' => 'hello@japacoach.com',
        'logo' => '/favicon.svg',
    ],

    'seo' => [
        // Public pages (titles, descriptions, sitemap data, markdown on/off) live in resources/content/seo.json,
        // shared with the Vue app. Robots.txt, llms.txt and ai.txt are generated from the same data.

        // Areas behind login: blocked in robots.txt so crawl budget goes to public pages.
        'disallow' => [
            '/dashboard', '/speaking', '/writing', '/reading', '/listening', '/practice', '/mock',
            '/study-plan', '/billing', '/referrals', '/settings', '/admin', '/api/', '/auth/', '/payment/',
        ],
    ],
];
