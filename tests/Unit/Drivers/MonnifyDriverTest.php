<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\MonnifyDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MonnifyDriverTest extends TestCase
{
    private function makeDriver(array $responses): MonnifyDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new MonnifyDriver([
            'driver'        => 'monnify',
            'api_key'       => 'MK_TEST_xxx',
            'secret_key'    => 'test_secret',
            'contract_code' => '1234567890',
            'base_url'      => 'https://api.monnify.com',
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
        $this->assertInstanceOf(VirtualAccountDriverInterface::class, $driver);
    }

    public function test_initialize_payment_fetches_token_then_creates_transaction(): void
    {
        $driver = $this->makeDriver([
            // Token request
            new Response(200, [], json_encode([
                'responseBody' => ['accessToken' => 'test_token_abc'],
            ])),
            // Init transaction
            new Response(200, [], json_encode([
                'responseBody' => [
                    'transactionReference' => 'MNFY|REF|001',
                    'checkoutUrl'          => 'https://checkout.monnify.com/pay/MNFY|REF|001',
                    'paymentReference'     => 'ORDER_001',
                ],
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'     => 'test@example.com',
            'name'      => 'Test User',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('responseBody', $response);
        $this->assertArrayHasKey('checkoutUrl', $response['responseBody']);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            // Token request
            new Response(200, [], json_encode([
                'responseBody' => ['accessToken' => 'test_token'],
            ])),
            // Verify
            new Response(200, [], json_encode([
                'responseBody' => [
                    'paymentReference'     => 'ORDER_001',
                    'paymentStatus'        => 'PAID',
                    'amountPaid'           => 5000,
                ],
            ])),
        ]);

        $response = $driver->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('responseBody', $response);
    }

    public function test_token_is_cached_between_calls(): void
    {
        $driver = $this->makeDriver([
            // Only ONE token request — proves caching works
            new Response(200, [], json_encode([
                'responseBody' => ['accessToken' => 'cached_token'],
            ])),
            new Response(200, [], json_encode([
                'responseBody' => ['transactionReference' => 'REF1', 'checkoutUrl' => 'https://example.com'],
            ])),
            new Response(200, [], json_encode([
                'responseBody' => ['paymentStatus' => 'PAID'],
            ])),
        ]);

        $driver->initializePayment([
            'email' => 'test@example.com', 'amount' => 1000, 'reference' => 'REF1',
        ]);
        $driver->verifyPayment('REF1');

        $this->assertTrue(true); // MockHandler would throw on extra token fetch
    }
}
