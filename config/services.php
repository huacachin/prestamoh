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

    /*
     * Anthropic (Claude): lectura automática del voucher del Anexo 2 (15/09).
     * La clave va SIEMPRE en .env, nunca en el repo — y .env está en
     * .gitignore y bloqueado por Apache (403) en producción.
     *
     * 'modelo' se deja configurable a propósito: la prueba de los 15 vouchers
     * dio 96% con el modelo grande, pero uno más barato puede dar lo mismo a
     * una fracción del costo. Se cambia por .env sin tocar código.
     * 'habilitado' permite apagar la lectura sin desinstalar nada: sin clave
     * el módulo sigue funcionando a mano.
     */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Sonnet 5 acertó 13/13 en los vouchers maestros igual que Opus 5, a
        // menos de la mitad del costo (medido el 15/09). Haiku 4.5 se queda en
        // 12/13: se le escapó un número de operación de 15 dígitos en una foto
        // de papel, y ahí no conviene ahorrar. Se cambia por .env sin tocar
        // código; el parámetro de esfuerzo se omite solo si el modelo no lo
        // acepta.
        'modelo' => env('ANTHROPIC_MODELO', 'claude-sonnet-5'),
        'habilitado' => filled(env('ANTHROPIC_API_KEY')),
    ],

];
