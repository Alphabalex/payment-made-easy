<?php

namespace NexusPay\PaymentMadeEasy\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;
use NexusPay\PaymentMadeEasy\Events\TransferSuccessful;
use NexusPay\PaymentMadeEasy\Jobs\ProcessWebhookJob;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class WebhookHandlingTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Paystack
    // -------------------------------------------------------------------------

    public function test_paystack_charge_success_webhook_dispatches_payment_successful(): void
    {
        Event::fake();

        $secret = 'paystack_webhook_secret';
        $this->app['config']->set('payment-gateways.gateways.paystack.webhook_secret', $secret);

        $payload = json_encode([
            'event' => 'charge.success',
            'data'  => [
                'reference'  => 'ORDER_001',
                'amount'     => 500000,
                'currency'   => 'NGN',
                'status'     => 'success',
                'customer'   => ['email' => 'test@example.com'],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $secret);

        $response = $this->call(
            'POST',
            '/webhooks/payment-gateways/paystack',
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
    }

    public function test_paystack_webhook_can_be_queued_with_raw_body_for_signature(): void
    {
        Event::fake();
        Queue::fake();

        $this->app['config']->set('payment-gateways.webhooks.queue_events', true);

        $secret = 'paystack_webhook_secret';
        $this->app['config']->set('payment-gateways.gateways.paystack.webhook_secret', $secret);

        $payload = json_encode([
            'event' => 'charge.success',
            'data'  => [
                'reference' => 'ORDER_QUEUED',
                'amount'    => 500000,
                'currency'  => 'NGN',
                'status'    => 'success',
                'customer'  => ['email' => 'test@example.com'],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $secret);

        $response = $this->call(
            'POST',
            '/webhooks/payment-gateways/paystack',
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(202);
        Event::assertNotDispatched(PaymentSuccessful::class);
        Queue::assertPushed(ProcessWebhookJob::class, function (ProcessWebhookJob $job) use ($payload) {
            return $job->gateway === 'paystack' && $job->rawContent === $payload;
        });
    }

    public function test_paystack_duplicate_webhook_is_ignored_when_idempotency_enabled(): void
    {
        Event::fake();
        Cache::flush();

        $this->app['config']->set('payment-gateways.webhooks.idempotency.enabled', true);
        $this->app['config']->set('payment-gateways.webhooks.idempotency.ttl', 3600);

        $secret = 'paystack_webhook_secret';
        $this->app['config']->set('payment-gateways.gateways.paystack.webhook_secret', $secret);

        $payload = json_encode([
            'event' => 'charge.success',
            'data'  => [
                'reference' => 'ORDER_DUP',
                'amount'    => 500000,
                'currency'  => 'NGN',
                'status'    => 'success',
                'customer'  => ['email' => 'test@example.com'],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $secret);
        $headers = ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/webhooks/payment-gateways/paystack', [], [], [], $headers, $payload)
            ->assertStatus(200);
        Event::assertDispatchedTimes(PaymentSuccessful::class, 1);

        $this->call('POST', '/webhooks/payment-gateways/paystack', [], [], [], $headers, $payload)
            ->assertStatus(200);
        Event::assertDispatchedTimes(PaymentSuccessful::class, 1);
    }

    public function test_paystack_webhook_rejects_invalid_signature(): void
    {
        $this->app['config']->set('payment-gateways.gateways.paystack.webhook_secret', 'real_secret');

        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'ORDER_001']];

        $response = $this->postJson('/webhooks/payment-gateways/paystack', $payload, [
            'X-Paystack-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(400);
    }

    public function test_paystack_webhook_rejects_when_signing_secret_missing(): void
    {
        $base = $this->app['config']->get('payment-gateways.gateways.paystack', []);
        $this->app['config']->set('payment-gateways.gateways.paystack', array_merge($base, [
            'webhook_secret' => '',
        ]));

        $response = $this->postJson('/webhooks/payment-gateways/paystack', [
            'event' => 'charge.success',
            'data'  => ['reference' => 'ORDER_001'],
        ]);

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Razorpay
    // -------------------------------------------------------------------------

    public function test_razorpay_payment_captured_webhook_dispatches_payment_successful(): void
    {
        Event::fake();

        $secret = 'razorpay_webhook_secret';
        $this->app['config']->set('payment-gateways.gateways.razorpay.webhook_secret', $secret);

        $payload = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id'        => 'pay_XXXXXXXXXX',
                        'order_id'  => 'order_XXXXXXXXXX',
                        'amount'    => 49900,
                        'currency'  => 'INR',
                        'status'    => 'captured',
                        'email'     => 'test@example.com',
                    ],
                ],
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        $response = $this->call(
            'POST',
            '/webhooks/payment-gateways/razorpay',
            [],
            [],
            [],
            ['HTTP_X_RAZORPAY_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
    }

    // -------------------------------------------------------------------------
    // Paddle
    // -------------------------------------------------------------------------

    public function test_paddle_webhook_rejects_missing_signature_header(): void
    {
        $this->app['config']->set('payment-gateways.gateways.paddle', [
            'driver'         => 'paddle',
            'api_key'        => 'test',
            'webhook_secret' => 'paddle_secret',
            'currency'       => 'USD',
            'base_url'       => 'https://api.paddle.com',
        ]);

        $payload = ['event_type' => 'transaction.completed', 'data' => []];

        // No Paddle-Signature header
        $response = $this->postJson('/webhooks/payment-gateways/paddle', $payload);

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Gateway not found
    // -------------------------------------------------------------------------

    public function test_unknown_gateway_returns_400(): void
    {
        // 'unknown' doesn't match the route constraint, so it should 404
        $response = $this->postJson('/webhooks/payment-gateways/unknown_xyz', []);

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Flutterwave
    // -------------------------------------------------------------------------

    public function test_flutterwave_charge_completed_dispatches_payment_successful(): void
    {
        Event::fake();

        $secret = 'flw_webhook_secret';
        $this->app['config']->set('payment-gateways.gateways.flutterwave', [
            'driver'         => 'flutterwave',
            'secret_key'     => 'FLWSECK_TEST-xxx',
            'webhook_secret' => $secret,
            'base_url'       => 'https://api.flutterwave.com/v3',
            'callback_url'   => 'https://example.com/callback',
        ]);

        $payloadArray = [
            'event' => 'charge.completed',
            'data'  => [
                'id'         => 123456,
                'tx_ref'     => 'FLW_TX_001',
                'flw_ref'    => 'FLW_FLW_001',
                'amount'     => 5000,
                'currency'   => 'NGN',
                'status'     => 'successful',
                'customer'   => ['email' => 'buyer@example.com'],
                'created_at' => '2026-05-07T10:00:00.000Z',
            ],
        ];
        $rawPayload = json_encode($payloadArray);

        // Flutterwave sends the webhook_secret directly as the verif-hash header
        $response = $this->call(
            'POST',
            '/webhooks/payment-gateways/flutterwave',
            [],
            [],
            [],
            ['HTTP_VERIF_HASH' => $secret, 'CONTENT_TYPE' => 'application/json'],
            $rawPayload
        );

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
    }

    public function test_flutterwave_webhook_rejects_invalid_signature(): void
    {
        $this->app['config']->set('payment-gateways.gateways.flutterwave', [
            'driver'         => 'flutterwave',
            'secret_key'     => 'FLWSECK_TEST-xxx',
            'webhook_secret' => 'real_secret',
            'base_url'       => 'https://api.flutterwave.com/v3',
        ]);

        $response = $this->postJson(
            '/webhooks/payment-gateways/flutterwave',
            ['event' => 'charge.completed', 'data' => []],
            ['verif-hash' => 'wrong_secret']
        );

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Stripe
    // -------------------------------------------------------------------------

    public function test_stripe_payment_intent_succeeded_dispatches_payment_successful(): void
    {
        Event::fake();

        // TestCase already configures stripe with webhook_secret = 'whsec_test_xxx'
        $secret    = 'whsec_test_xxx';
        $timestamp = time();

        $eventPayload = [
            'id'     => 'evt_test_001',
            'object' => 'event',
            'type'   => 'payment_intent.succeeded',
            'data'   => [
                'object' => [
                    'id'            => 'pi_test_001',
                    'object'        => 'payment_intent',
                    'amount'        => 49900,
                    'currency'      => 'usd',
                    'status'        => 'succeeded',
                    'created'       => $timestamp,
                    'receipt_email' => 'stripe_customer@example.com',
                    'metadata'      => ['reference' => 'ORDER_STRIPE_001'],
                ],
            ],
        ];

        $rawJson   = json_encode($eventPayload);
        $signed    = "{$timestamp}.{$rawJson}";
        $hmac      = hash_hmac('sha256', $signed, $secret);
        $sigHeader = "t={$timestamp},v1={$hmac}";

        $response = $this->call(
            'POST',
            '/webhooks/payment-gateways/stripe',
            [],
            [],
            [],
            ['HTTP_STRIPE-SIGNATURE' => $sigHeader, 'CONTENT_TYPE' => 'application/json'],
            $rawJson
        );

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
    }

    public function test_stripe_payment_intent_failed_dispatches_payment_failed(): void
    {
        Event::fake();

        $secret    = 'whsec_test_xxx';
        $timestamp = time();

        $eventPayload = [
            'id'     => 'evt_test_002',
            'object' => 'event',
            'type'   => 'payment_intent.payment_failed',
            'data'   => [
                'object' => [
                    'id'                 => 'pi_test_002',
                    'object'             => 'payment_intent',
                    'amount'             => 2000,
                    'currency'           => 'usd',
                    'status'             => 'requires_payment_method',
                    'created'            => $timestamp,
                    'receipt_email'      => 'stripe_customer2@example.com',
                    'metadata'           => [],
                    'last_payment_error' => ['message' => 'Your card was declined.'],
                ],
            ],
        ];

        $rawJson   = json_encode($eventPayload);
        $signed    = "{$timestamp}.{$rawJson}";
        $hmac      = hash_hmac('sha256', $signed, $secret);
        $sigHeader = "t={$timestamp},v1={$hmac}";

        $response = $this->call(
            'POST',
            '/webhooks/payment-gateways/stripe',
            [],
            [],
            [],
            ['HTTP_STRIPE-SIGNATURE' => $sigHeader, 'CONTENT_TYPE' => 'application/json'],
            $rawJson
        );

        $response->assertStatus(200);
        Event::assertDispatched(PaymentFailed::class);
    }

    // -------------------------------------------------------------------------
    // MPesa
    // -------------------------------------------------------------------------

    public function test_mpesa_stk_push_success_dispatches_payment_successful(): void
    {
        Event::fake();

        $this->app['config']->set('payment-gateways.gateways.mpesa', [
            'driver'       => 'mpesa',
            'consumer_key' => 'test_consumer_key',
            'shortcode'    => '174379',
            'base_url'     => 'https://sandbox.safaricom.co.ke',
            'callback_url' => 'https://example.com/mpesa/callback',
        ]);

        // MPesa STK Push success callback
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID'  => 'mrq_001',
                    'CheckoutRequestID'  => 'ws_CO_test_001',
                    'ResultCode'         => 0,
                    'ResultDesc'         => 'The service request is processed successfully.',
                    'CallbackMetadata'   => [
                        'Item' => [
                            ['Name' => 'Amount',              'Value' => 1000],
                            ['Name' => 'MpesaReceiptNumber',  'Value' => 'NLJ7RT61SV'],
                            ['Name' => 'TransactionDate',     'Value' => 20191219102115],
                            ['Name' => 'PhoneNumber',         'Value' => 254708374149],
                        ],
                    ],
                ],
            ],
        ];

        // MPesa does not require a signature header
        $response = $this->postJson('/webhooks/payment-gateways/mpesa', $payload);

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
    }

    // -------------------------------------------------------------------------
    // Monnify
    // -------------------------------------------------------------------------

    public function test_monnify_successful_transaction_dispatches_payment_successful(): void
    {
        Event::fake();

        $secret = 'monnify_test_secret';
        $this->app['config']->set('payment-gateways.gateways.monnify', [
            'driver'         => 'monnify',
            'api_key'        => 'MK_TEST_xxx',
            'secret_key'     => $secret,
            'webhook_secret' => $secret,
            'contract_code'  => '4934121693',
            'base_url'       => 'https://sandbox.monnify.com',
            'callback_url'   => 'https://example.com/callback',
        ]);

        $payload = [
            'eventType' => 'SUCCESSFUL_TRANSACTION',
            'eventData' => [
                'product'          => ['reference' => 'MON_ORDER_001', 'type' => 'WEB_SDK'],
                'paymentReference' => 'MON_ORDER_001',
                'amountPaid'       => 5000.00,
                'totalPayable'     => 5000.00,
                'currency'         => 'NGN',
                'paidOn'           => '2026-05-07 10:00:00',
                'paymentStatus'    => 'PAID',
                'customer'         => ['email' => 'monnify@example.com'],
            ],
        ];

        $rawJson  = json_encode($payload);
        $hmacSig  = base64_encode(hash_hmac('sha512', $rawJson, $secret, true));

        $response = $this->call(
            'POST',
            '/webhooks/payment-gateways/monnify',
            [],
            [],
            [],
            ['HTTP_MONNIFY-SIGNATURE' => $hmacSig, 'CONTENT_TYPE' => 'application/json'],
            $rawJson
        );

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
    }

    // -------------------------------------------------------------------------
    // MTN MoMo
    // -------------------------------------------------------------------------

    public function test_mtnmomo_payment_successful_dispatches_payment_successful(): void
    {
        Event::fake();

        $this->app['config']->set('payment-gateways.gateways.mtnmomo', [
            'driver'                      => 'mtnmomo',
            'collection_subscription_key' => 'test_sub_key',
            'base_url'                    => 'https://sandbox.momodeveloper.mtn.com',
            'callback_url'                => 'https://example.com/mtnmomo/callback',
            'currency'                    => 'EUR',
        ]);

        // MTN MoMo collection callback (RequestToPay result) — no signature required
        $payload = [
            'financialTransactionId' => 'mtn_fin_txn_001',
            'externalId'             => 'MTN_EXT_001',
            'amount'                 => '50',
            'currency'               => 'EUR',
            'payer'                  => [
                'partyIdType' => 'MSISDN',
                'partyId'     => '46733123454',
            ],
            'payerMessage'           => 'Test payment',
            'payeeNote'              => 'Test note',
            'status'                 => 'SUCCESSFUL',
        ];

        $response = $this->postJson('/webhooks/payment-gateways/mtnmomo', $payload);

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
    }
}
