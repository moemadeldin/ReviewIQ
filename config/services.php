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
        'webhook_url' => env('GITHUB_WEBHOOK_URL'),
        'base_url' => env('GITHUB_BASE_URL'),
        'scopes' => ['read:user', 'repo'],
    ],
    'github_app' => [
        'app_id' => env('GITHUB_APP_ID'),
        'installation_id' => env('GITHUB_APP_INSTALLATION_ID'),
        'private_key_path' => env('GITHUB_APP_PRIVATE_KEY_PATH', storage_path('oauth/reviewiq-pr-reviewer.pem')),
    ],
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1/'),
        'model' => env('OPENROUTER_MODEL', 'deepseek/deepseek-v4-flash:free'),
        'temperature' => (float) env('OPENROUTER_TEMPERATURE', 0.2),
        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 2000),
        'timeout' => env('OPENROUTER_TIMEOUT', 60),
    ],
];
