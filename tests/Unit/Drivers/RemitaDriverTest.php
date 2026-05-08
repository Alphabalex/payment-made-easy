<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\RemitaDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class RemitaDriverTest extends TestCase
{
    private function makeDriver(array $responses): RemitaDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new RemitaDriver([
            'driver'          => 'remita',
            'api_key'         => 'test_api_key',
            'secret_key'      => 'test_secret',
            'merchant_id'     => 'MERCHANT_001',
            'service_type_id' => 'SERVICE_001',
            'base_url'        => 'https://remitademo.net/payment/v1',
            'checkout_url'    => 'https://remitademo.net/payment/v1/pay',
            'callback_url'    => 'https://example.com/callback',
        ]);

        $reflection = new \ReflectionClass($driver);
        $prop = $reflection->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        return $driver;
    }

    public function test_implements_disbursement_interface(): void
    {
        $driver = $this->makeDriver([]);
        $this->assertInstanceOf(DisbursementDriverInterface::class, $driver);
    }

    public function test_initialize_payment_returns_rrr_with_redirect_url(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'statuscode'  => '025',
                'status'      => 'PAYMENT_PENDING',
                'RRR'         => 'RRR12345678',
                'transactionId' => 'TXN_001',
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'     => 'test@example.com',
            'name'      => 'Test User',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('RRR', $response);
        $this->assertArrayHasKey('authorization_url', $response);
        $this->assertStringContainsString('RRR12345678', $response['authorization_url']);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'    => 'SUCCESSFUL',
                'amount'    => 5000,
                'RRR'       => 'RRR12345678',
            ])),
        ]);

        $response = $driver->verifyPayment('RRR12345678');

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('SUCCESSFUL', $response['status']);
    }

    public function test_does_not_implement_subscription_interface(): void
    {
        $driver = $this->makeDriver([]);
        $this->assertNotInstanceOf(
            \NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface::class,
            $driver
        );
    }
}
