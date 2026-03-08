<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'qr_expiry_minutes' => (int) env('GATEWAY_QR_EXPIRY_MINUTES', 30),
    'webhook_retry_attempts' => (int) env('GATEWAY_WEBHOOK_RETRY_ATTEMPTS', 4),
    'webhook_retry_delays' => [0, 30, 120, 600], // seconds between retries
    'api_version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | ID Prefixes — opaque IDs like Stripe
    |--------------------------------------------------------------------------
    */

    'prefixes' => [
        'project' => 'proj_',
        'payment' => 'pay_',
        'event' => 'evt_',
        'webhook' => 'wh_',
        'api_key' => 'sk_test_',
        'balance_transaction' => 'btxn_',
        'payout' => 'po_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Methods
    |--------------------------------------------------------------------------
    */

    'payment_methods' => [
        'credit_card',
        'pix',
        'qrcode',
        'internal_balance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Statuses
    |--------------------------------------------------------------------------
    */

    'statuses' => [
        'created',
        'pending',
        'processing',
        'approved',
        'failed',
        'fraud',
        'canceled',
        'refunded',
    ],
];
