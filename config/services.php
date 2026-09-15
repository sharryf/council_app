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

    /*
    | Bureau's AI-assisted minutes drafting (see
    | App\Services\Bureau\MinutesDraftingService) — expands the Bureau
    | Admin's bullet points into formal Dhivehi minutes prose. Optional:
    | if unset, drafting simply doesn't auto-fill and the Admin types
    | the prose by hand instead. Uses Google's Gemini API (free tier via
    | Google AI Studio, https://aistudio.google.com/apikey) rather than
    | a paid provider.
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

];
