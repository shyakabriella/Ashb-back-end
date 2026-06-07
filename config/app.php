<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores configuration values for third-party services.
    | Secret values must be placed in the Laravel .env file.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    /*
    |--------------------------------------------------------------------------
    | Google Gemini AI
    |--------------------------------------------------------------------------
    |
    | Used by task creation to organize a rough task idea into a professional
    | task name, milestone, and description.
    |
    | The API key stays securely in the backend .env file.
    |
    */

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.3),
        'max_output_tokens' => (int) env(
            'GEMINI_MAX_OUTPUT_TOKENS',
            800
        ),
        'enabled' => filter_var(
            env('GEMINI_USE_AI', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

];