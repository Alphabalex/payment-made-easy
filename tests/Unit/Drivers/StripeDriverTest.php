<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Stripe\StripeClient;
use NexusPay\PaymentMadeEasy\Drivers\StripeDriver;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class StripeDriverTest extends TestCase
{
    private function config(): array
    {
        return [
            'driver'         => 'stripe',
            'secret_key'     => 'sk_test_xxx',
            'public_key'     => 'pk_test_xxx',
            'currency'       => 'usd',
            'base_url'       => 'https://api.stripe.com',
            'callback_url'   => 'https://example.com/callback',
            'webhook_secret' => 'whsec_test',
        ];
    }

    public function test_stripe_implements_subscription_interface(): void
    {
        $driver = new StripeDriver($this->config());
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $driver);
    }

    public function test_stripe_implements_payment_link_interface(): void
    {
        $driver = new StripeDriver($this->config());
        $this->assertInstanceOf(PaymentLinkDriverInterface::class, $driver);
    }

    public function test_stripe_does_not_implement_disbursement_interface(): void
    {
        $driver = new StripeDriver($this->config());
        $this->assertNotInstanceOf(DisbursementDriverInterface::class, $driver);
    }

    public function test_initialize_payment_throws_with_invalid_key(): void
    {
        // StripeDriver uses the stripe-php SDK, which will throw on invalid key
        $driver = new StripeDriver($this->config());

        $this->expectException(PaymentException::class);

        $driver->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 50.00,
        ]);
    }

    public function test_driver_accepts_currency_option(): void
    {
        $driver = new StripeDriver(array_merge($this->config(), ['currency' => 'gbp']));
        $this->assertInstanceOf(StripeDriver::class, $driver);
    }

    public function test_amount_conversion_is_correct(): void
    {
        $driver = new StripeDriver($this->config());

        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('convertAmount');
        $method->setAccessible(true);

        // Stripe uses cents — 29.99 USD should become 2999
        $this->assertEquals(2999, $method->invoke($driver, 29.99));
        $this->assertEquals(50000, $method->invoke($driver, 500.00));
    }
}
