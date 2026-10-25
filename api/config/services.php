<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'bachs' => [
        'secret_key' => env('BACHS_SECRET_KEY'),
        'webhook_secret' => env('BACHS_WEBHOOK_SECRET'),
        'currency' => env('BACHS_CURRENCY', 'NGN'),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],

    'speech' => [
        'url' => env('SPEECH_SERVICE_URL', 'http://127.0.0.1:8100'),
        'token' => env('SPEECH_SERVICE_TOKEN'),
    ],

    'fcm' => [
        // Base64 of the Firebase project's service account JSON key. Empty = reuse the Google Play key (see PushNotifier).
        'service_account' => env('FCM_SERVICE_ACCOUNT'),
    ],

    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.japacoach.app'),
        // Base64 of the service account JSON key with access to the app in Play Console.
        'service_account' => env('GOOGLE_PLAY_SERVICE_ACCOUNT'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];
