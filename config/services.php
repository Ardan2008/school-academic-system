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

    /*
    |--------------------------------------------------------------------------
    | Tim IT Sekolah
    |--------------------------------------------------------------------------
    |
    | Tujuan notifikasi untuk permintaan bantuan yang dikirim lewat pop-up
    | "Hubungi Tim IT Sekolah" di landing page. Kalau email kosong, notifikasi
    | tidak dikirim; permintaan tetap tersimpan di tabel it_help_requests.
    |
    */

    'it_support' => [
        'email' => env('IT_SUPPORT_EMAIL'),
        'name' => env('IT_SUPPORT_NAME', 'Tim IT Sekolah'),
    ],

];
