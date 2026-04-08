<?php

declare(strict_types=1);

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
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'base_url' => env('GITHUB_BASE_URL'),
        'scopes' => ['read:user', 'repo'],  // Add this
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_API_CHAT'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'temperature' => (int) env('GROQ_TEMPERATURE', 0.2),
        'max_tokens' => (int) env('GROQ_MAX_TOKENS', 2000),
        'timeout' => env('GROQ_TIMEOUT', 60),
    ],
];
