<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit;

use NexusPay\PaymentMadeEasy\GatewayRegistry;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class GatewayRegistryTest extends TestCase
{
    public function test_webhook_route_pattern_includes_all_gateways(): void
    {
        $pattern = GatewayRegistry::webhookRoutePattern();
        foreach (array_keys(GatewayRegistry::WEBHOOK_HANDLER_CLASSES) as $slug) {
            $this->assertStringContainsString($slug, $pattern);
        }
    }

    public function test_driver_and_webhook_keys_align(): void
    {
        $this->assertSame(
            array_keys(GatewayRegistry::DRIVER_CLASSES),
            array_keys(GatewayRegistry::WEBHOOK_HANDLER_CLASSES)
        );
    }
}
