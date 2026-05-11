<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\GatewayCapabilities;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class GatewayCapabilitiesTest extends TestCase
{
    public function test_paystack_supports_disbursement(): void
    {
        $this->assertTrue(
            GatewayCapabilities::driverImplements('paystack', DisbursementDriverInterface::class)
        );
    }

    public function test_stripe_does_not_support_disbursement(): void
    {
        $this->assertFalse(
            GatewayCapabilities::driverImplements('stripe', DisbursementDriverInterface::class)
        );
    }

    public function test_gateways_implementing_subscription_includes_flutterwave(): void
    {
        $slugs = GatewayCapabilities::gatewaysImplementing(SubscriptionDriverInterface::class);
        $this->assertContains('flutterwave', $slugs);
        $this->assertContains('paddle', $slugs);
    }

    public function test_optional_capabilities_returns_baseline_for_mpesa(): void
    {
        $caps = GatewayCapabilities::optionalCapabilities('mpesa');
        $this->assertFalse($caps['SubscriptionDriverInterface']);
        $this->assertFalse($caps['DisbursementDriverInterface']);
    }

    public function test_unknown_gateway_returns_false(): void
    {
        $this->assertFalse(
            GatewayCapabilities::driverImplements('not_a_gateway', SubscriptionDriverInterface::class)
        );
    }
}
