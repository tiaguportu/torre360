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

    'fcm' => [
        'key' => env('FCM_SERVER_KEY'),
        'url' => env('FCM_SERVER_URL', 'https://fcm.googleapis.com/fcm/send'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    // Chave da API do Google Books (busca de livros por ISBN). Opcional: sem ela a cota anônima é bem menor.
    // Precisa estar aqui: `env()` fora dos arquivos de config devolve null quando a configuração está em cache.
    'google_books' => [
        'key' => env('GOOGLE_BOOKS_API_KEY'),
    ],

    // Busca de livros por ISBN (App\Services\LivroLookupService).
    'livros' => [
        // A raspagem do HTML da Amazon e o CDN de capas deles são não oficiais e contrários aos termos de uso do site.
        // Desligado por padrão; a escola só liga assumindo esse risco (LIVROS_AMAZON_HABILITADO=true).
        'amazon_habilitado' => (bool) env('LIVROS_AMAZON_HABILITADO', false),

        // Resolve o DNS da URL da capa e recusa endereços internos/privados (proteção contra SSRF).
        'validar_dns' => (bool) env('LIVROS_VALIDAR_DNS', true),
    ],

    'assinafy' => [
        'url' => env('ASSINAFY_API_URL', 'https://sandbox.assinafy.com.br/v1'),
        'key' => env('ASSINAFY_API_KEY'),
        'account_id' => env('ASSINAFY_ACCOUNT_ID'),
        'webhook_secret' => env('ASSINAFY_WEBHOOK_SECRET'),
    ],

    'google_analytics' => [
        'measurement_id' => env('GOOGLE_ANALYTICS_ID'),
    ],

];
