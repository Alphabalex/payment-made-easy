<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit;

use NexusPay\PaymentMadeEasy\Tests\TestCase;
use NexusPay\PaymentMadeEasy\PaymentManager;
use NexusPay\PaymentMadeEasy\Contracts\PaymentDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\PaystackDriver;
use NexusPay\PaymentMadeEasy\Drivers\StripeDriver;

class PaymentManagerTest extends TestCase
{
    public function test_manager_resolves_from_container(): void
    {
        $manager = $this->app->make(PaymentManager::class);

        $this->assertInstanceOf(PaymentManager::class, $manager);
    }

    public function test_manager_resolves_via_alias(): void
    {
        $manager = $this->app->make('payment-gateways');

        $this->assertInstanceOf(PaymentManager::class, $manager);
    }

    public function test_default_driver_resolves(): void
    {
        $driver = $this->app->make(PaymentManager::class)->driver();

        $this->assertInstanceOf(PaymentDriverInterface::class, $driver);
        $this->assertInstanceOf(PaystackDriver::class, $driver);
    }

    public function test_can_resolve_named_driver(): void
    {
        $driver = $this->app->make(PaymentManager::class)->driver('paystack');

        $this->assertInstanceOf(PaystackDriver::class, $driver);
    }

    public function test_paystack_implements_all_expected_interfaces(): void
    {
        $driver = $this->app->make(PaymentManager::class)->driver('paystack');

        $this->assertInstanceOf(PaymentDriverInterface::class, $driver);
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $driver);
        $this->assertInstanceOf(DisbursementDriverInterface::class, $driver);
        $this->assertInstanceOf(VirtualAccountDriverInterface::class, $driver);
        $this->assertInstanceOf(PaymentLinkDriverInterface::class, $driver);
    }

    public function test_stripe_does_not_implement_disbursement(): void
    {
        $driver = $this->app->make(PaymentManager::class)->driver('stripe');

        $this->assertInstanceOf(PaymentDriverInterface::class, $driver);
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $driver);
        $this->assertInstanceOf(PaymentLinkDriverInterface::class, $driver);
        $this->assertNotInstanceOf(DisbursementDriverInterface::class, $driver);
        $this->assertNotInstanceOf(VirtualAccountDriverInterface::class, $driver);
    }

    public function test_driver_returns_same_instance_when_called_twice(): void
    {
        $manager = $this->app->make(PaymentManager::class);

        $driver1 = $manager->driver('paystack');
        $driver2 = $manager->driver('paystack');

        $this->assertSame($driver1, $driver2);
    }

    public function test_unknown_driver_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(PaymentManager::class)->driver('nonexistent_gateway');
    }

}
