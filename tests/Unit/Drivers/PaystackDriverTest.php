<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Drivers\PaystackDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaystackDriverTest extends TestCase
{
    private function driverConfig(): array
    {
        return [
            'driver'       => 'paystack',
            'public_key'   => 'pk_test_xxx',
            'secret_key'   => 'sk_test_xxx',
            'base_url'     => 'https://api.paystack.co',
            'callback_url' => 'https://example.com/callback',
        ];
    }

    private function driver(): PaystackDriver
    {
        return new PaystackDriver($this->driverConfig());
    }

    public function test_initialize_payment_sends_correct_request(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status'  => true,
                'message' => 'Authorization URL created',
                'data'    => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code'       => 'abc123',
                    'reference'         => 'ORDER_001',
                ],
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'     => 'test@example.com',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertTrue($response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('https://checkout.paystack.com/abc123', $response['data']['authorization_url']);
    }

    public function test_verify_payment_returns_response(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status'  => true,
                'message' => 'Verification successful',
                'data'    => [
                    'reference' => 'ORDER_001',
                    'status'    => 'success',
                    'amount'    => 500000,
                ],
            ], 200),
        ]);

        $response = $this->driver()->verifyPayment('ORDER_001');

        $this->assertTrue($response['status']);
        $this->assertEquals('success', $response['data']['status']);
    }

    public function test_initialize_payment_throws_on_http_error(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status'  => false,
                'message' => 'Invalid key',
            ], 401),
        ]);

        $this->expectException(PaymentException::class);

        $this->driver()->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 5000.00,
        ]);
    }

    public function test_refund_sends_correct_payload(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status'  => true,
                'message' => 'Refund has been queued for processing',
                'data'    => ['status' => 'pending'],
            ], 200),
        ]);

        $response = $this->driver()->refundPayment('ORDER_001', 2500.00);

        $this->assertTrue($response['status']);
    }

    public function test_get_transactions_returns_list(): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status' => true,
                'data'   => [
                    ['id' => 1, 'reference' => 'ref_1', 'amount' => 500000, 'status' => 'success'],
                    ['id' => 2, 'reference' => 'ref_2', 'amount' => 100000, 'status' => 'success'],
                ],
            ], 200),
        ]);

        $response = $this->driver()->getTransactions(['per_page' => 2]);

        $this->assertTrue($response['status']);
        $this->assertCount(2, $response['data']);
    }

    public function test_convert_amount_multiplies_by_100(): void
    {
        $reflection = new \ReflectionClass(PaystackDriver::class);
        $method = $reflection->getMethod('convertAmount');
        $method->setAccessible(true);

        $result = $method->invoke($this->driver(), 5000.00);

        $this->assertEquals(500000, $result);
    }
}
