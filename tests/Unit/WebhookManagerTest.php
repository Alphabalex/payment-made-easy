<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit;

use NexusPay\PaymentMadeEasy\Tests\TestCase;
use NexusPay\PaymentMadeEasy\WebhookManager;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\PaystackWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\StripeWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\RazorpayWebhookHandler;

class WebhookManagerTest extends TestCase
{
    private WebhookManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(WebhookManager::class);
    }

    public function test_manager_resolves_from_container(): void
    {
        $this->assertInstanceOf(WebhookManager::class, $this->manager);
    }

    public function test_manager_resolves_via_alias(): void
    {
        $this->assertInstanceOf(WebhookManager::class, $this->app->make('payment-webhooks'));
    }

    public function test_can_get_paystack_handler(): void
    {
        $handler = $this->manager->getHandler('paystack');
        $this->assertInstanceOf(PaystackWebhookHandler::class, $handler);
    }

    public function test_can_get_stripe_handler(): void
    {
        $handler = $this->manager->getHandler('stripe');
        $this->assertInstanceOf(StripeWebhookHandler::class, $handler);
    }

    public function test_can_get_razorpay_handler(): void
    {
        // Razorpay config must be present for the handler to be registered.
        // defineEnvironment in TestCase sets it up.
        $handler = $this->manager->getHandler('razorpay');
        $this->assertInstanceOf(RazorpayWebhookHandler::class, $handler);
    }

    public function test_unknown_gateway_throws_webhook_exception(): void
    {
        $this->expectException(WebhookException::class);
        $this->manager->getHandler('nonexistent_gateway');
    }
}
