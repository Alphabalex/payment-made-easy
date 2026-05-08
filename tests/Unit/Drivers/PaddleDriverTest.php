<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\PaddleDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaddleDriverTest extends TestCase
{
    private function makeDriver(array $responses): PaddleDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new PaddleDriver([
            'driver'   => 'paddle',
            'api_key'  => 'pdl_snd_xxx',
            'base_url' => 'https://sandbox-api.paddle.com',
            'callback_url' => 'https://example.com/callback',
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
        $this->assertInstanceOf(PaymentLinkDriverInterface::class, $driver);
    }

    public function test_does_not_implement_disbursement_interface(): void
    {
        $driver = $this->makeDriver([]);
        $this->assertNotInstanceOf(
            \NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface::class,
            $driver
        );
    }

    public function test_initialize_payment_creates_transaction(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'data' => [
                    'id'       => 'txn_01abc',
                    'status'   => 'ready',
                    'checkout' => [
                        'url' => 'https://checkout.paddle.com/checkout/txn_01abc',
                    ],
                ],
            ])),
        ]);

        $response = $driver->initializePayment([
            'currency'  => 'USD',
            'reference' => 'ORDER_001',
            'items'     => [['price_id' => 'pri_abc', 'quantity' => 1]],
        ]);

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('txn_01abc', $response['data']['id']);
    }

    public function test_verify_payment_returns_transaction(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'data' => [
                    'id'     => 'txn_01abc',
                    'status' => 'completed',
                    'details' => [
                        'totals' => ['grand_total' => '2999', 'currency_code' => 'USD'],
                    ],
                ],
            ])),
        ]);

        $response = $driver->verifyPayment('txn_01abc');

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('completed', $response['data']['status']);
    }
}
