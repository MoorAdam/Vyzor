<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'clarity' => [
        'endpoint' => 'https://www.clarity.ms/export-data/api/v1/project-live-insights',
        'token' => env('CLARITY_KEY'),
        'fetch_daily_limit' => 10,
        'connect_timeout' => env('CLARITY_CONNECT_TIMEOUT', 10),
        'timeout' => env('CLARITY_TIMEOUT', 90),
        'fetch_max_seconds' => env('CLARITY_FETCH_MAX_SECONDS', 180),

        // Nightly auto-fetch — see app:fetch-clarity. Time is HH:MM in the app timezone.
        // Schedule it just before midnight so numOfDays=1 (rolling 24h) lines up with the
        // calendar day it represents.
        'auto_fetch' => [
            'enabled' => env('CLARITY_AUTO_FETCH_ENABLED', false),
            'time' => env('CLARITY_AUTO_FETCH_TIME', '23:55'),
        ],
    ],

    'htmlFetcher' => [
        'timeout' => env('HTML_FETCHER_TIMEOUT', 10),
        'max_body_bytes' => env('HTML_FETCHER_MAX_BODY_BYTES', 2 * 1024 * 1024), // 2 MB
    ],

];
