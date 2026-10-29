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

    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-5'),
    ],

    // ── Correo del despacho (Microsoft 365): borradores con Microsoft Graph ─────
    // Aplicación registrada en Microsoft Entra ID con el permiso Mail.ReadWrite limitado a un único buzón.
    // Los valores van solo en el .env del servidor; nunca en el código ni en el chat.
    'microsoft' => [
        'enabled'       => (bool) env('MS_DRAFTS_ENABLED', false),
        'tenant'        => env('MS_TENANT_ID'),
        'client_id'     => env('MS_CLIENT_ID'),
        'client_secret' => env('MS_CLIENT_SECRET'),
        'mailbox'       => env('MS_MAILBOX'),
    ],

    // ── Integración con Power Automate / Microsoft 365 ───────────────────
    // Entrada de documentos por WhatsApp: integración heredada, APAGADA por defecto. Ver WhatsAppWebhookController.
    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_WEBHOOK_ENABLED', false),
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        'media_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('WHATSAPP_MEDIA_HOSTS', 'graph.facebook.com,lookaside.fbsbx.com,*.fbcdn.net,*.whatsapp.net'))))),
        'max_bytes' => 15 * 1024 * 1024,
    ],

    'power_automate' => [
        // Token Bearer que Power Automate debe enviar en cada petición entrante
        'api_token'        => env('POWER_AUTOMATE_API_TOKEN'),
        // Segundos máximos de espera al hacer el callback de vuelta a Power Automate
        'callback_timeout' => env('POWER_AUTOMATE_CALLBACK_URL_TIMEOUT', 10),
    ],

];
