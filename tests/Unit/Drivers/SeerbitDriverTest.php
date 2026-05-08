<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\SeerbitDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class SeerbitDriverTest extends TestCase
{
    private function makeDriver(array $responses): SeerbitDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new SeerbitDriver([
            'driver'       => 'seerbit',
            'public_key'   => 'SBPUBK_xxx',
            'secret_key'   => 'SBSECK_xxx',
            'base_url'     => 'https://seerbitapi.com/api/v2',
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
        $this->assertInstanceOf(VirtualAccountDriverInterface::class, $driver);
    }

    public function test_initialize_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => 'SUCCESS',
                'message' => 'SUCCESS',
                'data'    => [
                    'payments' => [
                        'redirectLink' => 'https://pay.seerbit.com/checkout/abc123',
                        'paymentReference' => 'ORDER_001',
                    ],
                ],
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'     => 'test@example.com',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('SUCCESS', $response['status']);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => 'SUCCESS',
                'data'    => [
                    'payments' => [
                        'paymentReference' => 'ORDER_001',
                        'transactionStatus' => 'SUCCESSFUL',
                    ],
                ],
            ])),
        ]);

        $response = $driver->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('status', $response);
    }

    public function test_does_not_implement_disbursement_interface(): void
    {
        $driver = $this->makeDriver([]);
        $this->assertNotInstanceOf(
            \NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface::class,
            $driver
        );
    }
}
