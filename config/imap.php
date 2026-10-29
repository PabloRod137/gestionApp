<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Info / Account
    |--------------------------------------------------------------------------
    |
    | Default account to be used by the IMAP Client.
    |
    */
    'default' => env('IMAP_DEFAULT_ACCOUNT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | IMAP Accounts configuration
    |--------------------------------------------------------------------------
    |
    | Define the accounts here that you want to connect to.
    |
    */
    'accounts' => [

        'default' => [
            'host'           => env('IMAP_HOST', '127.0.0.1'),
            'port'           => env('IMAP_PORT', 993),
            'protocol'       => env('IMAP_PROTOCOL', 'imap'), // imap, pop3, nntp
            'encryption'     => env('IMAP_ENCRYPTION', 'ssl'), // ssl, tls, or false
            'validate_cert'  => env('IMAP_VALIDATE_CERT', true),
            'username'       => env('IMAP_USERNAME', ''),
            'password'       => env('IMAP_PASSWORD', ''),
            'authentication' => env('IMAP_AUTHENTICATION', null),
            'proxy' => [
                'socket'          => null,
                'request_fulluri' => false,
                'username'        => null,
                'password'        => null,
            ]
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | IMAP Options
    |--------------------------------------------------------------------------
    |
    | General options.
    |
    */
    'options' => [
        'delimiter' => '/',
        'fetch' => \Webklex\PHPIMAP\IMAP::FT_UID,
        'fetch_body' => true,
        'fetch_flags' => true,
        'soft_fail' => false,
        'rfc822' => true,
        'debug' => false,
        'boundary' => '/\s+/',
        'message_key' => 'id',
        'fetch_order' => 'desc',
        'disposition_trigger' => ['attachment', 'inline'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Flags
    |--------------------------------------------------------------------------
    |
    | Default flags used.
    |
    */
    'flags' => ['recent', 'flagged', 'answered', 'deleted', 'seen', 'draft'],
];
