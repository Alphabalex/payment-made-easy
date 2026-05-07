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
        ],

        'flutterwave' => [
            'driver' => 'flutterwave',
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
            'callback_url' => env('FLUTTERWAVE_CALLBACK_URL'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        ],

        'stripe' => [
            'driver' => 'stripe',
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'callback_url' => env('STRIPE_CALLBACK_URL'),
        ],

        'seerbit' => [
            'driver' => 'seerbit',
            'public_key' => env('SEERBIT_PUBLIC_KEY'),
            'secret_key' => env('SEERBIT_SECRET_KEY'),
            'base_url' => env('SEERBIT_BASE_URL', 'https://seerbitapi.com/api/v2'),
            'callback_url' => env('SEERBIT_CALLBACK_URL'),
            'webhook_secret' => env('SEERBIT_WEBHOOK_SECRET'),
        ],

        'monnify' => [
            'driver'                  => 'monnify',
            'api_key'                 => env('MONNIFY_API_KEY'),
            'secret_key'              => env('MONNIFY_SECRET_KEY'),
            'contract_code'           => env('MONNIFY_CONTRACT_CODE'),
            'wallet_account_number'   => env('MONNIFY_WALLET_ACCOUNT_NUMBER'),
            'base_url'                => env('MONNIFY_BASE_URL', 'https://api.monnify.com'),
            'callback_url'            => env('MONNIFY_CALLBACK_URL'),
            'webhook_secret'          => env('MONNIFY_WEBHOOK_SECRET'),
        ],

        'squad' => [
            'driver'               => 'squad',
            'secret_key'           => env('SQUAD_SECRET_KEY'),
            'public_key'           => env('SQUAD_PUBLIC_KEY'),
            'base_url'             => env('SQUAD_BASE_URL', 'https://api.squadco.com'),
            'callback_url'         => env('SQUAD_CALLBACK_URL'),
            'webhook_secret'       => env('SQUAD_WEBHOOK_SECRET'),
            'beneficiary_account'  => env('SQUAD_BENEFICIARY_ACCOUNT'),
        ],

        'remita' => [
            'driver'          => 'remita',
            'api_key'         => env('REMITA_API_KEY'),
            'merchant_id'     => env('REMITA_MERCHANT_ID'),
            'service_type_id' => env('REMITA_SERVICE_TYPE_ID'),
            'source_account'  => env('REMITA_SOURCE_ACCOUNT'),
            'base_url'        => env('REMITA_BASE_URL', 'https://api.remita.net/remita'),
            'checkout_url'    => env('REMITA_CHECKOUT_URL', 'https://login.remita.net/remita/ecomm/finalize.reg'),
            'callback_url'    => env('REMITA_CALLBACK_URL'),
        ],

        'budpay' => [
            'driver'         => 'budpay',
            'secret_key'     => env('BUDPAY_SECRET_KEY'),
            'public_key'     => env('BUDPAY_PUBLIC_KEY'),
            'base_url'       => env('BUDPAY_BASE_URL', 'https://api.budpay.com/api/v2'),
            'callback_url'   => env('BUDPAY_CALLBACK_URL'),
            'webhook_secret' => env('BUDPAY_WEBHOOK_SECRET'),
        ],

        'interswitch' => [
            'driver'          => 'interswitch',
            'client_id'       => env('INTERSWITCH_CLIENT_ID'),
            'client_secret'   => env('INTERSWITCH_CLIENT_SECRET'),
            'merchant_code'   => env('INTERSWITCH_MERCHANT_CODE'),
            'payable_code'    => env('INTERSWITCH_PAYABLE_CODE'),
            'terminal_id'     => env('INTERSWITCH_TERMINAL_ID'),
            'passport_url'    => env('INTERSWITCH_PASSPORT_URL', 'https://passport.interswitchng.com'),
            'base_url'        => env('INTERSWITCH_BASE_URL', 'https://api.interswitchgroup.com'),
            'checkout_url'    => env('INTERSWITCH_CHECKOUT_URL', 'https://webpay.interswitchng.com/collections/w/pay'),
            'callback_url'    => env('INTERSWITCH_CALLBACK_URL'),
            'webhook_secret'  => env('INTERSWITCH_WEBHOOK_SECRET'),
        ],
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
                // Payments
                'charge.success'                     => 'payment.successful',
                'charge.failed'                      => 'payment.failed',
                // Refunds
                'refund.processed'                   => 'refund.processed',
                // Transfers / Disbursements
                'transfer.success'                   => 'transfer.successful',
                'transfer.failed'                    => 'transfer.failed',
                'transfer.reversed'                  => 'transfer.failed',
                // Subscriptions
                'subscription.create'                => 'subscription.created',
                'subscription.disable'               => 'subscription.cancelled',
                'subscription.expiry_date_update'    => 'subscription.renewed',
                // Invoices (subscription renewals)
                'invoice.create'                     => 'subscription.renewed',
                'invoice.update'                     => 'subscription.renewed',
                'invoice.payment_failed'             => 'payment.failed',
                // Disputes
                'dispute.create'                     => 'dispute.created',
                'dispute.remind'                     => 'dispute.created',
            ],
            'flutterwave' => [
                // Payments
                'charge.completed'                   => 'payment.successful',
                'charge.failed'                      => 'payment.failed',
                // Transfers / Disbursements
                'transfer.completed'                 => 'transfer.successful',
                'transfer.failed'                    => 'transfer.failed',
                // Subscriptions
                'subscription.activated'             => 'subscription.created',
                'subscription.cancelled'             => 'subscription.cancelled',
                // Disputes / Chargebacks
                'dispute.raised'                     => 'dispute.created',
                'chargeback.raised'                  => 'chargeback.created',
            ],
            'stripe' => [
                // Payments
                'payment_intent.succeeded'           => 'payment.successful',
                'payment_intent.payment_failed'      => 'payment.failed',
                // Invoices (subscription renewals)
                'invoice.payment_succeeded'          => 'subscription.renewed',
                'invoice.payment_failed'             => 'payment.failed',
                // Subscriptions
                'customer.subscription.created'      => 'subscription.created',
                'customer.subscription.deleted'      => 'subscription.cancelled',
                'customer.subscription.updated'      => 'subscription.renewed',
                // Transfers / Payouts
                'payout.paid'                        => 'transfer.successful',
                'payout.failed'                      => 'transfer.failed',
                // Disputes / Chargebacks
                'charge.dispute.created'             => 'dispute.created',
                'radar.early_fraud_warning.created'  => 'chargeback.created',
            ],
            'seerbit' => [
                // Payments
                'TRANSACTION_SUCCESSFUL'             => 'payment.successful',
                'TRANSACTION_FAILED'                 => 'payment.failed',
                'TRANSACTION_PENDING'                => 'payment.pending',
                // Refunds
                'REFUND_SUCCESSFUL'                  => 'refund.processed',
                'REFUND_FAILED'                      => 'refund.failed',
                // Subscriptions
                'SUBSCRIPTION_CREATED'               => 'subscription.created',
                'SUBSCRIPTION_CANCELLED'             => 'subscription.cancelled',
                'SUBSCRIPTION_RENEWED'               => 'subscription.renewed',
                'SUBSCRIPTION_PAYMENT_FAILED'        => 'payment.failed',
            ],

            'monnify' => [
                // Payments
                'SUCCESSFUL_TRANSACTION'             => 'payment.successful',
                'FAILED_TRANSACTION'                 => 'payment.failed',
                // Virtual account funded
                'RESERVED_ACCOUNT_FUNDED'            => 'payment.successful',
                // Disbursements
                'DISBURSEMENT_COMPLETED'             => 'transfer.successful',
                'DISBURSEMENT_FAILED'                => 'transfer.failed',
                'BULK_DISBURSEMENT_COMPLETED'        => 'transfer.successful',
                'BULK_DISBURSEMENT_FAILED'           => 'transfer.failed',
                // Refunds
                'REFUND_COMPLETED'                   => 'refund.processed',
            ],

            'squad' => [
                // Payments
                'charge_successful'                  => 'payment.successful',
                'charge_failed'                      => 'payment.failed',
                // Virtual account
                'virtual_account_payment'            => 'payment.successful',
                // Transfers
                'transfer_complete'                  => 'transfer.successful',
                'transfer_failed'                    => 'transfer.failed',
            ],

            'remita' => [
                // Payments (event type is mapped internally from responseCode)
                'PAYMENT_SUCCESSFUL'                 => 'payment.successful',
                'PAYMENT_PENDING'                    => 'payment.pending',
                'PAYMENT_FAILED'                     => 'payment.failed',
                // Transfers
                'TRANSFER_SUCCESSFUL'                => 'transfer.successful',
                'TRANSFER_FAILED'                    => 'transfer.failed',
            ],

            'budpay' => [
                // Payments
                'transaction.successful'             => 'payment.successful',
                'transaction.failed'                 => 'payment.failed',
                // Virtual account funded
                'virtual-account.successful'         => 'payment.successful',
                // Payouts
                'payout.successful'                  => 'transfer.successful',
                'payout.failed'                      => 'transfer.failed',
                // Refunds
                'refund.successful'                  => 'refund.processed',
            ],

            'interswitch' => [
                // Event type is mapped internally from responseCode
                'PAYMENT_SUCCESSFUL'                 => 'payment.successful',
                'PAYMENT_PENDING'                    => 'payment.pending',
                'PAYMENT_FAILED'                     => 'payment.failed',
            ],

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
