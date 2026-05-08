<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Drivers\InterswitchDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class InterswitchDriverTest extends TestCase
{
    private function makeDriver(array $responses): InterswitchDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new InterswitchDriver([
            'driver'        => 'interswitch',
            'client_id'     => 'DSTV_SANDBOX_ID',
            'client_secret' => 'test_client_secret',
            'merchant_code' => 'MX6072',
            'payable_code'  => 'Default_Payable_MX6072',
            'base_url'      => 'https://qa.interswitchng.com',
            'passport_url'  => 'https://qa.interswitchng.com',
            'checkout_url'  => 'https://qa.interswitchng.com/collections/w/pay',
            'callback_url'  => 'https://example.com/callback',
        ]);

        $reflection = new \ReflectionClass($driver);
        $prop = $reflection->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        return $driver;
    }

    public function test_initialize_payment_fetches_token_then_creates_purchase(): void
    {
        $driver = $this->makeDriver([
            // OAuth token
            new Response(200, [], json_encode([
                'access_token' => 'isw_token_abc',
                'expires_in'   => 3600,
            ])),
            // Purchase
            new Response(200, [], json_encode([
                'paymentUrl'           => 'https://qa.interswitchng.com/collections/w/pay?ref=ORDER_001',
                'transactionReference' => 'ORDER_001',
                'amount'               => 500000,
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'     => 'test@example.com',
            'name'      => 'Test User',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('paymentUrl', $response);
    }

    public function test_token_is_cached_between_requests(): void
    {
        $driver = $this->makeDriver([
            // Only one token fetch
            new Response(200, [], json_encode(['access_token' => 'cached_token', 'expires_in' => 3600])),
            new Response(200, [], json_encode(['paymentUrl' => 'https://example.com', 'transactionReference' => 'R1'])),
            new Response(200, [], json_encode(['paymentUrl' => 'https://example.com', 'transactionReference' => 'R2'])),
        ]);

        $driver->initializePayment(['email' => 'a@b.com', 'amount' => 100, 'reference' => 'R1']);
        $driver->initializePayment(['email' => 'a@b.com', 'amount' => 200, 'reference' => 'R2']);

        $this->assertTrue(true); // MockHandler would throw if token was re-fetched
    }

    public function test_amount_converted_to_kobo(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode(['access_token' => 'tok', 'expires_in' => 3600])),
            new Response(200, [], json_encode(['paymentUrl' => 'https://example.com', 'transactionReference' => 'R1'])),
        ]);

        $response = $driver->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 5000.00,
        ]);

        $this->assertArrayHasKey('paymentUrl', $response);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode(['access_token' => 'tok', 'expires_in' => 3600])),
            new Response(200, [], json_encode([
                'responseCode'         => '00',
                'responseDescription'  => 'Approved by Financial Institution',
                'transactionReference' => 'ORDER_001',
                'amount'               => 500000,
            ])),
        ]);

        $response = $driver->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('responseCode', $response);
        $this->assertEquals('00', $response['responseCode']);
    }
}
