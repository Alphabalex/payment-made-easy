<?php

namespace NexusPay\PaymentMadeEasy\Tests\Feature;

use Illuminate\Support\Facades\Event;
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\TransferSuccessful;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;
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

        $response = $this->postJson('/webhooks/payment-gateways/paystack', json_decode($payload, true), [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        Event::assertDispatched(PaymentSuccessful::class);
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

        $response = $this->postJson('/webhooks/payment-gateways/razorpay', json_decode($payload, true), [
            'X-Razorpay-Signature' => $signature,
        ]);

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
}
