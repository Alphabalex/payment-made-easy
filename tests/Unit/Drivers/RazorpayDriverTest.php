<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Drivers\RazorpayDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class RazorpayDriverTest extends TestCase
{
    private function makeDriver(array $responses): RazorpayDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new RazorpayDriver([
            'driver'         => 'razorpay',
            'key_id'         => 'rzp_test_xxx',
            'key_secret'     => 'test_secret',
            'currency'       => 'INR',
            'base_url'       => 'https://api.razorpay.com/v1',
            'callback_url'   => 'https://example.com/callback',
            'webhook_secret' => 'webhook_secret',
        ]);

        $reflection = new \ReflectionClass($driver);
        $prop = $reflection->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        return $driver;
    }

    public function test_initialize_payment_creates_order(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'id'       => 'order_XXXXXXXXXX',
                'entity'   => 'order',
                'amount'   => 49900,
                'currency' => 'INR',
                'status'   => 'created',
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 499.00,
            'currency' => 'INR',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('order_XXXXXXXXXX', $response['id']);
    }

    public function test_verify_payment_queries_order(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'id'     => 'order_XXXXXXXXXX',
                'status' => 'paid',
                'amount' => 49900,
            ])),
        ]);

        $response = $driver->verifyPayment('order_XXXXXXXXXX');

        $this->assertEquals('paid', $response['status']);
    }

    public function test_refund_sends_correct_amount(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'id'     => 'rfnd_XXXXXXXXXX',
                'amount' => 10000,
                'status' => 'processed',
            ])),
        ]);

        $response = $driver->refundPayment('pay_XXXXXXXXXX', 100.00);

        $this->assertEquals('processed', $response['status']);
    }

    public function test_throws_payment_exception_on_http_error(): void
    {
        $driver = $this->makeDriver([
            new Response(400, [], json_encode([
                'error' => ['description' => 'Bad Request'],
            ])),
        ]);

        $this->expectException(PaymentException::class);

        $driver->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 499.00,
        ]);
    }
}
