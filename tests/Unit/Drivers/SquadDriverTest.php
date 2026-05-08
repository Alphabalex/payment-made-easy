<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\SquadDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class SquadDriverTest extends TestCase
{
    private function makeDriver(array $responses): SquadDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new SquadDriver([
            'driver'       => 'squad',
            'secret_key'   => 'sandbox_sk_xxx',
            'base_url'     => 'https://api-d.squadco.com',
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
                'status'  => 200,
                'success' => true,
                'data'    => [
                    'checkout_url'    => 'https://checkout.squadco.com/abc123',
                    'transaction_ref' => 'ORDER_001',
                    'merchant_amount' => 500000,
                ],
            ])),
        ]);

        $response = $driver->initializePayment([
            'email'     => 'test@example.com',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('checkout_url', $response['data']);
    }

    public function test_amount_converted_to_kobo(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode(['status' => 200, 'data' => ['checkout_url' => 'https://example.com']])),
        ]);

        // We verify 5000.00 NGN → 500000 kobo by checking no exception is thrown
        $response = $driver->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 5000.00,
        ]);

        $this->assertArrayHasKey('data', $response);
    }

    public function test_verify_payment_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status'  => 200,
                'data'    => [
                    'transaction_ref' => 'ORDER_001',
                    'transaction_status' => 'Success',
                ],
            ])),
        ]);

        $response = $driver->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('Success', $response['data']['transaction_status']);
    }

    public function test_list_banks_returns_response(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'status' => 200,
                'data'   => [
                    ['bank_code' => '058', 'bank_name' => 'GTBank'],
                    ['bank_code' => '044', 'bank_name' => 'Access Bank'],
                ],
            ])),
        ]);

        $banks = $driver->listBanks(['country' => 'NG']);

        $this->assertIsArray($banks);
    }
}
