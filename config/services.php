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

    // migo.pe: solo tipo de cambio (las consultas de documentos migraron a Factiliza)
    'migo' => [
        'token' => env('MIGO_PE_TOKEN'),
        'base' => 'https://api.migo.pe/api/v1',
    ],

    // Factiliza: consulta de DNI / RUC / carné de extranjería / placa.
    // El token va SIEMPRE en .env (nunca en el repo).
    'factiliza' => [
        'token' => env('FACTILIZA_TOKEN'),
        'base' => env('FACTILIZA_BASE', 'https://api.factiliza.com/v1'),
    ],

];
