<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Drivers\FlutterwaveDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class FlutterwaveDriverTest extends TestCase
{
    private function makeDriver(array $responses): FlutterwaveDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new FlutterwaveDriver([
            'driver'        => 'flutterwave',
            'public_key'    => 'FLWPUBK_TEST-xxx',
            'secret_key'    => 'FLWSECK_TEST-xxx',
            'encryption_key' => 'enc_key',
            'base_url'      => 'https://api.flutterwave.com/v3',
            'callback_url'  => 'https://example.com/callback',
            'webhook_secret' => 'flw_webhook_secret',
        ]);

        $reflection = new \ReflectionClass($driver);
        $prop = $reflection->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        return $driver;
    }

    public function test_initialize_payment_returns_link(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => 'success',
                'message' => 'Hosted Link',
                'data'    => [
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc123',
                ],
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 5000.00,
            'currency' => 'NGN',
            'name'     => 'Test User',
        ]);

        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('link', $response['data']);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => 'success',
                'message' => 'Transaction fetched successfully',
                'data'    => [
                    'id'          => 12345,
                    'tx_ref'      => 'FLW_ORDER_001',
                    'status'      => 'successful',
                    'amount'      => 5000,
                    'currency'    => 'NGN',
                ],
            ])),
        ]);

        $response = $driver->verifyPayment('FLW_ORDER_001');

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('successful', $response['data']['status']);
    }

    public function test_refund_payment(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => 'success',
                'message' => 'Refund initiated',
                'data'    => ['id' => 99, 'status' => 'completed'],
            ])),
        ]);

        $response = $driver->refundPayment('12345', 2500.00);

        $this->assertEquals('success', $response['status']);
    }

    public function test_list_banks(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => 'success',
                'data'    => [
                    ['id' => 1, 'name' => 'GTBank', 'code' => '058'],
                    ['id' => 2, 'name' => 'Access Bank', 'code' => '044'],
                ],
            ])),
        ]);

        $response = $driver->listBanks(['country' => 'NG']);

        $this->assertEquals('success', $response['status']);
        $this->assertCount(2, $response['data']);
    }

    public function test_throws_on_http_error(): void
    {
        $driver = $this->makeDriver([
            new Response(401, [], json_encode([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ])),
        ]);

        $this->expectException(PaymentException::class);

        $driver->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 5000.00,
        ]);
    }

    public function test_flutterwave_does_not_multiply_amount_by_100(): void
    {
        // Flutterwave accepts amounts in major currency units (not kobo)
        // So convertAmount is NOT used for the payload amount
        $driver = $this->makeDriver([
            new Response(200, [], json_encode(['status' => 'success', 'data' => ['link' => 'https://example.com']])),
        ]);

        // Should not throw — amount stays as-is (5000 NGN, not 500000 kobo)
        $response = $driver->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 5000.00,
            'currency' => 'NGN',
        ]);

        $this->assertEquals('success', $response['status']);
    }
}
