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

    'phone_auth' => [
        'otp_length' => env('PHONE_AUTH_OTP_LENGTH', 6),
        'otp_ttl_minutes' => env('PHONE_AUTH_OTP_TTL_MINUTES', 5),
        'token_ttl_minutes' => env('PHONE_AUTH_TOKEN_TTL_MINUTES', 43200),
    ],

    'eds_auth' => [
        'challenge_ttl_minutes' => env('EDS_AUTH_CHALLENGE_TTL_MINUTES', 5),
        'token_ttl_minutes' => env('EDS_AUTH_TOKEN_TTL_MINUTES', 43200),
        'verifier_driver' => env('EDS_AUTH_VERIFIER_DRIVER', 'auto'),
        'verifier_url' => env('EDS_AUTH_VERIFIER_URL', 'http://127.0.0.1:5055'),
    ],

];
