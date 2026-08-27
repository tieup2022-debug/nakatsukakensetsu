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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-5.6-luna'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 90),
    ],

    'board_image_analysis' => [
        'enabled' => env('BOARD_IMAGE_AI_ENABLED', true),
        'scheduled_limit' => max(1, (int) env('BOARD_IMAGE_AI_SCHEDULED_LIMIT', 2)),
        'max_dimension' => max(512, (int) env('BOARD_IMAGE_AI_MAX_DIMENSION', 1600)),
    ],

];
