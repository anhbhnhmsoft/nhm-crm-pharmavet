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
    'facebook' => [
        'client_id' => env('META_CLIENT_ID'),
        'client_secret' => env('META_CLIENT_SECRET'),
        'redirect' => env('META_REDIRECT'),
    ],
    'google' => [
        'map_key_api' => env('GOOGLE_MAPS_API_KEY'),
        'map_id' => env('GOOGLE_MAP_ID'),
    ],

    'stringee' => [
        'sid' => env('STRINGEE_SID'),
        'secret' => env('STRINGEE_SECRET'),
        'from_number' => env('STRINGEE_FROM_NUMBER'),
    ],

    'exchangerate' => [
        'api_key' => env('V6_API_KEY'),
        'base_url' => env('URL_API_V6'),
    ],

];
