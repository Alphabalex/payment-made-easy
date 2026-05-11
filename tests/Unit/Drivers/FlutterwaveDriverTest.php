<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Drivers\FlutterwaveDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class FlutterwaveDriverTest extends TestCase
{
    private function driverConfig(): array
    {
        return [
            'driver'         => 'flutterwave',
            'public_key'     => 'FLWPUBK_TEST-xxx',
            'secret_key'     => 'FLWSECK_TEST-xxx',
            'encryption_key' => 'enc_key',
            'base_url'       => 'https://api.flutterwave.com/v3',
            'callback_url'   => 'https://example.com/callback',
            'webhook_secret' => 'flw_webhook_secret',
        ];
    }

    private function driver(): FlutterwaveDriver
    {
        return new FlutterwaveDriver($this->driverConfig());
    }

    public function test_initialize_payment_returns_link(): void
    {
        Http::fake([
            'https://api.flutterwave.com/*' => Http::response([
                'status'  => 'success',
                'message' => 'Hosted Link',
                'data'    => [
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc123',
                ],
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
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
        Http::fake([
            'https://api.flutterwave.com/*' => Http::response([
                'status'  => 'success',
                'message' => 'Transaction fetched successfully',
                'data'    => [
                    'id'       => 12345,
                    'tx_ref'   => 'FLW_ORDER_001',
                    'status'   => 'successful',
                    'amount'   => 5000,
                    'currency' => 'NGN',
                ],
            ], 200),
        ]);

        $response = $this->driver()->verifyPayment('FLW_ORDER_001');

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('successful', $response['data']['status']);
    }

    public function test_refund_payment(): void
    {
        Http::fake([
            'https://api.flutterwave.com/*' => Http::response([
                'status'  => 'success',
                'message' => 'Refund initiated',
                'data'    => ['id' => 99, 'status' => 'completed'],
            ], 200),
        ]);

        $response = $this->driver()->refundPayment('12345', 2500.00);

        $this->assertEquals('success', $response['status']);
    }

    public function test_list_banks(): void
    {
        Http::fake([
            'https://api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'data'   => [
                    ['id' => 1, 'name' => 'GTBank', 'code' => '058'],
                    ['id' => 2, 'name' => 'Access Bank', 'code' => '044'],
                ],
            ], 200),
        ]);

        $response = $this->driver()->listBanks(['country' => 'NG']);

        $this->assertEquals('success', $response['status']);
        $this->assertCount(2, $response['data']);
    }

    public function test_throws_on_http_error(): void
    {
        Http::fake([
            'https://api.flutterwave.com/*' => Http::response([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ], 401),
        ]);

        $this->expectException(PaymentException::class);

        $this->driver()->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 5000.00,
        ]);
    }

    public function test_flutterwave_does_not_multiply_amount_by_100(): void
    {
        Http::fake([
            'https://api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'data'   => ['link' => 'https://example.com'],
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 5000.00,
            'currency' => 'NGN',
        ]);

        $this->assertEquals('success', $response['status']);
    }
}
