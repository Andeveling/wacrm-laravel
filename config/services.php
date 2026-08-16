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
    |--------------------------------------------------------------------------
    | Meta WhatsApp Business Platform
    |--------------------------------------------------------------------------
    |
    | `app_secret` firma el X-Hub-Signature-256 que Meta adjunta a cada
    | entrega del webhook. Su ausencia hace fallar cerrado el endpoint
    | (`VerifyMetaWebhookSignature` rechaza todo el tráfico), por lo
    | que el operador nunca termina aceptando entregas no firmadas sin
    | notarlo.
    |
    | `webhook_verify_token` es el token de desafío que Meta compara
    | contra `hub.verify_token` en el `GET` inicial de registro del
    | callback. Compartido por la Meta App — cada cuenta sigue trayendo
    | su propio `phone_number_id` y access_token.
    |
    | `graph_api_*` se usará en #65 (cliente Graph para envío y
    | multimedia); la URL por defecto apunta al cluster público.
    */
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'graph_api_url' => env('META_GRAPH_API_URL', 'https://graph.facebook.com'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v21.0'),
    ],

];
