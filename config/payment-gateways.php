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
    | HTTP client — Laravel Http facade
    |--------------------------------------------------------------------------
    |
    | Gateway API calls use Illuminate\Support\Facades\Http (timeout / verify
    | from each gateway's http_timeout and http_verify). For proxies, custom
    | handlers, or global defaults, use Laravel's API, for example in a service
    | provider boot():
    |
    |   use Illuminate\Support\Facades\Http;
    |   Http::globalOptions(['verify' => true, 'timeout' => 60]);
    |   Http::globalRequestMiddleware(fn ($request) => $request->withHeader('X-App', 'payments'));
    |
    */
    'http' => [],

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

        'paypal' => [
            'driver'        => 'paypal',
            'client_id'     => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'currency'      => env('PAYPAL_CURRENCY', 'USD'),
            'base_url'      => env('PAYPAL_BASE_URL', 'https://api.paypal.com'),
            'callback_url'  => env('PAYPAL_CALLBACK_URL'),
            'cancel_url'    => env('PAYPAL_CANCEL_URL'),
            'webhook_id'    => env('PAYPAL_WEBHOOK_ID'),
        ],

        'mpesa' => [
            'driver'              => 'mpesa',
            'consumer_key'        => env('MPESA_CONSUMER_KEY'),
            'consumer_secret'     => env('MPESA_CONSUMER_SECRET'),
            'shortcode'           => env('MPESA_SHORTCODE'),
            'passkey'             => env('MPESA_PASSKEY'),
            'initiator_name'      => env('MPESA_INITIATOR_NAME'),
            'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
            'base_url'            => env('MPESA_BASE_URL', 'https://api.safaricom.co.ke'),
            'callback_url'        => env('MPESA_CALLBACK_URL'),
            'result_url'          => env('MPESA_RESULT_URL'),
            'timeout_url'         => env('MPESA_TIMEOUT_URL'),
        ],

        'mtnmomo' => [
            'driver'                          => 'mtnmomo',
            'collection_user_id'              => env('MTNMOMO_COLLECTION_USER_ID'),
            'collection_api_key'              => env('MTNMOMO_COLLECTION_API_KEY'),
            'collection_subscription_key'     => env('MTNMOMO_COLLECTION_SUBSCRIPTION_KEY'),
            'disbursement_user_id'            => env('MTNMOMO_DISBURSEMENT_USER_ID'),
            'disbursement_api_key'            => env('MTNMOMO_DISBURSEMENT_API_KEY'),
            'disbursement_subscription_key'   => env('MTNMOMO_DISBURSEMENT_SUBSCRIPTION_KEY'),
            'environment'                     => env('MTNMOMO_ENVIRONMENT', 'sandbox'),
            'currency'                        => env('MTNMOMO_CURRENCY', 'EUR'),
            'base_url'                        => env('MTNMOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'callback_url'                    => env('MTNMOMO_CALLBACK_URL'),
        ],

        'razorpay' => [
            'driver'         => 'razorpay',
            'key_id'         => env('RAZORPAY_KEY_ID'),
            'key_secret'     => env('RAZORPAY_KEY_SECRET'),
            'account_number' => env('RAZORPAY_ACCOUNT_NUMBER'),
            'currency'       => env('RAZORPAY_CURRENCY', 'INR'),
            'base_url'       => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
            'callback_url'   => env('RAZORPAY_CALLBACK_URL'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        ],

        'paddle' => [
            'driver'         => 'paddle',
            'api_key'        => env('PADDLE_API_KEY'),
            'client_token'   => env('PADDLE_CLIENT_TOKEN'),
            'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
            'currency'       => env('PADDLE_CURRENCY', 'USD'),
            'base_url'       => env('PADDLE_BASE_URL', 'https://api.paddle.com'),
            'callback_url'   => env('PADDLE_CALLBACK_URL'),
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
        // When true with verify_signature, gateways that use a shared signing secret reject requests if it is missing (fail closed).
        'require_signing_secret' => env('PAYMENT_WEBHOOK_REQUIRE_SIGNING_SECRET', true),
        'log_events' => env('PAYMENT_WEBHOOK_LOG_EVENTS', true),
        'queue_events' => env('PAYMENT_WEBHOOK_QUEUE_EVENTS', false),
        'queue_connection' => env('PAYMENT_WEBHOOK_QUEUE_CONNECTION', 'default'),
        'queue_name' => env('PAYMENT_WEBHOOK_QUEUE_NAME', 'payment-webhooks'),

        // full: gateway, event_type, payload data | minimal: gateway and event_type only
        'log_detail' => env('PAYMENT_WEBHOOK_LOG_DETAIL', 'full'),

        // When true and log_detail=full, webhook / error logs run through WebhookLogSanitizerInterface
        'log_sanitize' => env('PAYMENT_WEBHOOK_LOG_SANITIZE', true),
        // Optional FQCN implementing WebhookLogSanitizerInterface (null = DefaultWebhookLogSanitizer)
        'log_sanitizer' => env('PAYMENT_WEBHOOK_LOG_SANITIZER'),
        // Extra keys to redact (merged with package defaults). Keys ending with _secret, _token, etc. are also redacted.
        'log_redact_keys' => [],

        // null = include stack trace only when config('app.debug') is true; true/false to force on or off for unexpected webhook errors (HTTP 500 path).
        'log_unexpected_exception_trace' => env('PAYMENT_WEBHOOK_LOG_UNEXPECTED_TRACE'),

        // Optional route middleware, e.g. ['throttle:60,1']
        'middleware' => [],

        /*
        | Dedupe retries: first request wins; on handler failure the lock is released so the gateway can retry.
        */
        'idempotency' => [
            'enabled' => env('PAYMENT_WEBHOOK_IDEMPOTENCY_ENABLED', false),
            'ttl' => (int) env('PAYMENT_WEBHOOK_IDEMPOTENCY_TTL', 86400),
            'cache_prefix' => env('PAYMENT_WEBHOOK_IDEMPOTENCY_PREFIX', 'payment-webhook'),
        ],

        /*
        | Rate limit webhook HTTP requests (per IP + gateway). Add
        | \NexusPay\PaymentMadeEasy\Http\Middleware\ThrottlePaymentWebhooks::class
        | to webhooks.middleware when enabled.
        */
        'throttle' => [
            'enabled' => env('PAYMENT_WEBHOOK_THROTTLE_ENABLED', false),
            'max_attempts' => (int) env('PAYMENT_WEBHOOK_THROTTLE_MAX_ATTEMPTS', 60),
            'decay_seconds' => (int) env('PAYMENT_WEBHOOK_THROTTLE_DECAY_SECONDS', 60),
            'cache_prefix' => env('PAYMENT_WEBHOOK_THROTTLE_PREFIX', 'payment-webhook-throttle'),
        ],

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

            'paypal' => [
                // Payments
                'PAYMENT.CAPTURE.COMPLETED'          => 'payment.successful',
                'PAYMENT.CAPTURE.DENIED'             => 'payment.failed',
                'PAYMENT.CAPTURE.PENDING'            => 'payment.pending',
                'CHECKOUT.ORDER.COMPLETED'           => 'payment.successful',
                // Refunds
                'PAYMENT.CAPTURE.REFUNDED'           => 'refund.processed',
                // Subscriptions
                'BILLING.SUBSCRIPTION.ACTIVATED'     => 'subscription.created',
                'BILLING.SUBSCRIPTION.CANCELLED'     => 'subscription.cancelled',
                'BILLING.SUBSCRIPTION.PAYMENT.COMPLETED' => 'subscription.renewed',
                'BILLING.SUBSCRIPTION.PAYMENT.FAILED'    => 'payment.failed',
                // Payouts
                'PAYMENT.PAYOUTS-ITEM.SUCCEEDED'     => 'transfer.successful',
                'PAYMENT.PAYOUTS-ITEM.FAILED'        => 'transfer.failed',
                // Disputes
                'CUSTOMER.DISPUTE.CREATED'           => 'dispute.created',
                'RISK.DISPUTE.CREATED'               => 'chargeback.created',
            ],

            'mpesa' => [
                // STK Push (collections)
                'STK_PUSH_SUCCESS'                   => 'payment.successful',
                'STK_PUSH_FAILED'                    => 'payment.failed',
                // C2B
                'C2B_PAYMENT'                        => 'payment.successful',
                // B2C disbursements
                'B2C_SUCCESS'                        => 'transfer.successful',
                'B2C_FAILED'                         => 'transfer.failed',
            ],

            'mtnmomo' => [
                // Collections (RequestToPay)
                'PAYMENT_SUCCESSFUL'                 => 'payment.successful',
                'PAYMENT_PENDING'                    => 'payment.pending',
                'PAYMENT_FAILED'                     => 'payment.failed',
                // Disbursements (Transfer)
                'TRANSFER_SUCCESSFUL'                => 'transfer.successful',
                'TRANSFER_FAILED'                    => 'transfer.failed',
            ],

            'razorpay' => [
                // Payments
                'payment.captured'                   => 'payment.successful',
                'payment.failed'                     => 'payment.failed',
                'order.paid'                         => 'payment.successful',
                // Refunds
                'refund.created'                     => 'refund.processed',
                'refund.processed'                   => 'refund.processed',
                // Subscriptions
                'subscription.activated'             => 'subscription.created',
                'subscription.charged'               => 'subscription.renewed',
                'subscription.cancelled'             => 'subscription.cancelled',
                'subscription.paused'                => 'subscription.cancelled',
                'subscription.resumed'               => 'subscription.created',
                'subscription.pending'               => 'payment.pending',
                // Payouts
                'payout.processed'                   => 'transfer.successful',
                'payout.failed'                      => 'transfer.failed',
                // Payment links
                'payment_link.paid'                  => 'payment.successful',
                // Disputes
                'dispute.created'                    => 'dispute.created',
                'dispute.won'                        => 'dispute.created',
            ],

            'paddle' => [
                // Transactions
                'transaction.completed'              => 'payment.successful',
                'transaction.payment_failed'         => 'payment.failed',
                'transaction.updated'                => 'payment.pending',
                // Refunds / adjustments
                'adjustment.created'                 => 'refund.processed',
                'adjustment.updated'                 => 'refund.processed',
                // Subscriptions
                'subscription.activated'             => 'subscription.created',
                'subscription.canceled'              => 'subscription.cancelled',
                'subscription.updated'               => 'subscription.created',
                'subscription.past_due'              => 'payment.failed',
                'subscription.paused'                => 'subscription.cancelled',
                'subscription.resumed'               => 'subscription.created',
                'subscription.trialing'              => 'subscription.created',
                // Disputes
                'dispute.created'                    => 'dispute.created',
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

    /*
    |--------------------------------------------------------------------------
    | Database Recording
    |--------------------------------------------------------------------------
    |
    | recording.enabled: master switch for persistence helpers.
    | recording.auto_from_webhook_events: when both are true, package listeners
    | map PaymentSuccessful / transfers / subscriptions etc. into PaymentRecorder.
    | You can still call PaymentRecorder manually regardless of auto_from_webhook_events.
    |
    | Publish migrations:
    |   php artisan vendor:publish --tag=payment-gateways-migrations
    |   php artisan migrate
    |
    */
    'recording' => [
        'enabled' => env('PAYMENT_RECORDING_ENABLED', false),
        'log_webhooks' => env('PAYMENT_RECORD_WEBHOOKS', true),
        'auto_from_webhook_events' => env('PAYMENT_RECORDING_AUTO_WEBHOOK_EVENTS', false),
    ],
];
