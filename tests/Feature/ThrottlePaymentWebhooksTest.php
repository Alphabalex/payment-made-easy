<?php

namespace NexusPay\PaymentMadeEasy\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use NexusPay\PaymentMadeEasy\Http\Middleware\ThrottlePaymentWebhooks;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class ThrottlePaymentWebhooksTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('payment-gateways.webhooks.middleware', [
            ThrottlePaymentWebhooks::class,
        ]);
        $app['config']->set('payment-gateways.webhooks.throttle.enabled', true);
        $app['config']->set('payment-gateways.webhooks.throttle.max_attempts', 3);
        $app['config']->set('payment-gateways.webhooks.throttle.decay_seconds', 120);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_fourth_request_returns_429(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/webhooks/payment-gateways/paystack', [])
                ->assertStatus(400);
        }

        $this->postJson('/webhooks/payment-gateways/paystack', [])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }
}
