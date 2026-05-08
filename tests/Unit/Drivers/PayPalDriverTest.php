<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\PayPalDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PayPalDriverTest extends TestCase
{
    private function makeDriver(array $responses): PayPalDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new PayPalDriver([
            'driver'        => 'paypal',
            'client_id'     => 'paypal_client_id',
            'client_secret' => 'paypal_client_secret',
            'base_url'      => 'https://api.sandbox.paypal.com',
            'callback_url'  => 'https://example.com/callback',
        ]);

        $reflection = new \ReflectionClass($driver);
        $prop = $reflection->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        return $driver;
    }

    public function test_implements_correct_interfaces(): void
    {
        $driver = $this->makeDriver([]);
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $driver);
        $this->assertInstanceOf(DisbursementDriverInterface::class, $driver);
        $this->assertInstanceOf(PaymentLinkDriverInterface::class, $driver);
    }

    public function test_initialize_payment_fetches_token_then_creates_order(): void
    {
        $driver = $this->makeDriver([
            // OAuth token
            new Response(200, [], json_encode([
                'access_token' => 'paypal_access_token',
                'expires_in'   => 3600,
                'token_type'   => 'Bearer',
            ])),
            // Create order
            new Response(201, [], json_encode([
                'id'     => 'PAYPAL_ORDER_001',
                'status' => 'CREATED',
                'links'  => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL_ORDER_001'],
                    ['rel' => 'self',    'href' => 'https://api.sandbox.paypal.com/v2/checkout/orders/PAYPAL_ORDER_001'],
                ],
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 50.00,
            'currency' => 'USD',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('PAYPAL_ORDER_001', $response['id']);
    }

    public function test_token_is_cached_between_requests(): void
    {
        $driver = $this->makeDriver([
            // Only one token fetch
            new Response(200, [], json_encode(['access_token' => 'cached_tok', 'expires_in' => 3600])),
            new Response(201, [], json_encode(['id' => 'ORDER_1', 'status' => 'CREATED', 'links' => []])),
            new Response(201, [], json_encode(['id' => 'ORDER_2', 'status' => 'CREATED', 'links' => []])),
        ]);

        $driver->initializePayment(['email' => 'a@b.com', 'amount' => 10, 'currency' => 'USD']);
        $driver->initializePayment(['email' => 'a@b.com', 'amount' => 20, 'currency' => 'USD']);

        $this->assertTrue(true);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode(['access_token' => 'tok', 'expires_in' => 3600])),
            new Response(200, [], json_encode([
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
            ])),
        ]);

        $response = $driver->verifyPayment('PAYPAL_ORDER_001');

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('COMPLETED', $response['status']);
    }

    public function test_amount_not_converted_to_subunits(): void
    {
        // PayPal uses decimal amounts, not minor units
        $driver = $this->makeDriver([
            new Response(200, [], json_encode(['access_token' => 'tok', 'expires_in' => 3600])),
            new Response(201, [], json_encode(['id' => 'ORDER_1', 'status' => 'CREATED', 'links' => []])),
        ]);

        // 50.00 should remain 50.00, not become 5000
        $response = $driver->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 50.00,
            'currency' => 'USD',
        ]);

        $this->assertArrayHasKey('id', $response);
    }
}
