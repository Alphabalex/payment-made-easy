<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Drivers\RazorpayDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class RazorpayDriverTest extends TestCase
{
    private function driver(): RazorpayDriver
    {
        return new RazorpayDriver([
            'driver'         => 'razorpay',
            'key_id'         => 'rzp_test_xxx',
            'key_secret'     => 'test_secret',
            'currency'       => 'INR',
            'base_url'       => 'https://api.razorpay.com/v1',
            'callback_url'   => 'https://example.com/callback',
            'webhook_secret' => 'webhook_secret',
        ]);
    }

    public function test_initialize_payment_creates_order(): void
    {
        Http::fake([
            'https://api.razorpay.com/*' => Http::response([
                'id'       => 'order_XXXXXXXXXX',
                'entity'   => 'order',
                'amount'   => 49900,
                'currency' => 'INR',
                'status'   => 'created',
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'    => 'test@example.com',
            'amount'   => 499.00,
            'currency' => 'INR',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('order_XXXXXXXXXX', $response['id']);
    }

    public function test_verify_payment_queries_order(): void
    {
        Http::fake([
            'https://api.razorpay.com/*' => Http::response([
                'id'     => 'order_XXXXXXXXXX',
                'status' => 'paid',
                'amount' => 49900,
            ], 200),
        ]);

        $response = $this->driver()->verifyPayment('order_XXXXXXXXXX');

        $this->assertEquals('paid', $response['status']);
    }

    public function test_refund_sends_correct_amount(): void
    {
        Http::fake([
            'https://api.razorpay.com/*' => Http::response([
                'id'     => 'rfnd_XXXXXXXXXX',
                'amount' => 10000,
                'status' => 'processed',
            ], 200),
        ]);

        $response = $this->driver()->refundPayment('pay_XXXXXXXXXX', 100.00);

        $this->assertEquals('processed', $response['status']);
    }

    public function test_throws_payment_exception_on_http_error(): void
    {
        Http::fake([
            'https://api.razorpay.com/*' => Http::response([
                'error' => ['description' => 'Bad Request'],
            ], 400),
        ]);

        $this->expectException(PaymentException::class);

        $this->driver()->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 499.00,
        ]);
    }
}
