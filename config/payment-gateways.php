<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | by the payment manager. You may change this to any of the gateways
    | defined in the "gateways" array below.
    |
    */
    'default' => env('PAYMENT_GATEWAY', 'paystack'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure the payment gateways for your application.
    | Each gateway has its own configuration options.
    |
    */
    'gateways' => [
        'paystack' => [
            'driver' => 'paystack',
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'callback_url' => env('PAYSTACK_CALLBACK_URL'),
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'enabled' => env('PAYMENT_WEBHOOKS_ENABLED', true),
        'verify_signature' => env('PAYMENT_WEBHOOK_VERIFY_SIGNATURE', true),
        'log_events' => env('PAYMENT_WEBHOOK_LOG_EVENTS', true),
        'queue_events' => env('PAYMENT_WEBHOOK_QUEUE_EVENTS', false),
        'queue_connection' => env('PAYMENT_WEBHOOK_QUEUE_CONNECTION', 'default'),
        'queue_name' => env('PAYMENT_WEBHOOK_QUEUE_NAME', 'payment-webhooks'),
        
        // Event mapping for different gateways
        'event_mapping' => [
            'paystack' => [
                'charge.success' => 'payment.successful',
                'charge.failed' => 'payment.failed',
                'transfer.success' => 'transfer.successful',
                'transfer.failed' => 'transfer.failed',
                'refund.processed' => 'refund.processed',
            ]
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for payments
    |
    */
    'currency' => env('PAYMENT_CURRENCY', 'NGN'),
];