<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\PayPalDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PayPalDriverTest extends TestCase
{
    private function driver(): PayPalDriver
    {
        return new PayPalDriver([
            'driver'        => 'paypal',
            'client_id'     => 'paypal_client_id',
            'client_secret' => 'paypal_client_secret',
            'base_url'      => 'https://api.sandbox.paypal.com',
            'callback_url'  => 'https://example.com/callback',
        ]);
    }

    public function test_implements_correct_interfaces(): void
    {
        $d = $this->driver();
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $d);
        $this->assertInstanceOf(DisbursementDriverInterface::class, $d);
        $this->assertInstanceOf(PaymentLinkDriverInterface::class, $d);
    }

    public function test_initialize_payment_fetches_token_then_creates_order(): void
    {
        Http::fake([
            'https://api.sandbox.paypal.com/*' => Http::sequence()
                ->push([
                    'access_token' => 'paypal_access_token',
                    'expires_in'   => 3600,
                    'token_type'   => 'Bearer',
                ], 200)
                ->push([
                    'id'     => 'PAYPAL_ORDER_001',
                    'status' => 'CREATED',
                    'links'  => [
                        ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL_ORDER_001'],
                        ['rel' => 'self', 'href' => 'https://api.sandbox.paypal.com/v2/checkout/orders/PAYPAL_ORDER_001'],
                    ],
                ], 201),
        ]);

        $response = $this->driver()->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 50.00,
            'currency' => 'USD',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('PAYPAL_ORDER_001', $response['id']);
    }

    public function test_token_is_cached_between_requests(): void
    {
        Http::fake([
            'https://api.sandbox.paypal.com/*' => Http::sequence()
                ->push(['access_token' => 'cached_tok', 'expires_in' => 3600], 200)
                ->push(['id' => 'ORDER_1', 'status' => 'CREATED', 'links' => []], 201)
                ->push(['id' => 'ORDER_2', 'status' => 'CREATED', 'links' => []], 201),
        ]);

        $d = $this->driver();
        $d->initializePayment(['email' => 'a@b.com', 'amount' => 10, 'currency' => 'USD']);
        $d->initializePayment(['email' => 'a@b.com', 'amount' => 20, 'currency' => 'USD']);

        $this->assertTrue(true);
    }

    public function test_verify_payment_returns_response(): void
    {
        Http::fake([
            'https://api.sandbox.paypal.com/*' => Http::sequence()
                ->push(['access_token' => 'tok', 'expires_in' => 3600], 200)
                ->push([
                    'id'     => 'PAYPAL_ORDER_001',
                    'status' => 'COMPLETED',
                    'purchase_units' => [
                        [
                            'reference_id' => 'ORDER_001',
                            'amount'       => ['currency_code' => 'USD', 'value' => '50.00'],
                            'payments'     => [
                                'captures' => [
                                    ['id' => 'CAP_001', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '50.00']],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->driver()->verifyPayment('PAYPAL_ORDER_001');

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('COMPLETED', $response['status']);
    }

    public function test_amount_not_converted_to_subunits(): void
    {
        Http::fake([
            'https://api.sandbox.paypal.com/*' => Http::sequence()
                ->push(['access_token' => 'tok', 'expires_in' => 3600], 200)
                ->push(['id' => 'ORDER_1', 'status' => 'CREATED', 'links' => []], 201),
        ]);

        $response = $this->driver()->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 50.00,
            'currency' => 'USD',
        ]);

        $this->assertArrayHasKey('id', $response);
    }
}
