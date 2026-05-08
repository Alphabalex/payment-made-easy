<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\BudpayDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class BudpayDriverTest extends TestCase
{
    private function makeDriver(array $responses): BudpayDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new BudpayDriver([
            'driver'       => 'budpay',
            'secret_key'   => 'sk_test_xxx',
            'base_url'     => 'https://api.budpay.com/api/v2',
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
        $this->assertInstanceOf(DisbursementDriverInterface::class, $driver);
        $this->assertInstanceOf(VirtualAccountDriverInterface::class, $driver);
    }

    public function test_initialize_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => true,
                'message' => 'Authorization URL created',
                'data'    => [
                    'authorization_url' => 'https://checkout.budpay.com/abc123',
                    'access_code'       => 'abc123',
                    'reference'         => 'ORDER_001',
                ],
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'     => 'test@example.com',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('authorization_url', $response['data']);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => true,
                'message' => 'Verification successful',
                'data'    => [
                    'reference' => 'ORDER_001',
                    'status'    => 'success',
                    'amount'    => '5000.00',
                ],
            ])),
        ]);

        $response = $driver->verifyPayment('ORDER_001');

        $this->assertTrue($response['status']);
        $this->assertEquals('success', $response['data']['status']);
    }

    public function test_amount_sent_as_string(): void
    {
        // Budpay expects amount as a string, not an integer
        $driver = $this->makeDriver([
            new Response(200, [], json_encode(['status' => true, 'data' => ['authorization_url' => 'https://example.com']])),
        ]);

        $response = $driver->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 2500.50,
        ]);

        $this->assertArrayHasKey('data', $response);
    }
}
