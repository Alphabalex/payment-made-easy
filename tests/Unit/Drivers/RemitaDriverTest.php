<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\RemitaDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class RemitaDriverTest extends TestCase
{
    private function driver(): RemitaDriver
    {
        return new RemitaDriver([
            'driver'          => 'remita',
            'api_key'         => 'test_api_key',
            'secret_key'      => 'test_secret',
            'merchant_id'     => 'MERCHANT_001',
            'service_type_id' => 'SERVICE_001',
            'base_url'        => 'https://remitademo.net/payment/v1',
            'checkout_url'    => 'https://remitademo.net/payment/v1/pay',
            'callback_url'    => 'https://example.com/callback',
        ]);
    }

    public function test_implements_disbursement_interface(): void
    {
        $this->assertInstanceOf(DisbursementDriverInterface::class, $this->driver());
    }

    public function test_initialize_payment_returns_rrr_with_redirect_url(): void
    {
        Http::fake([
            'https://remitademo.net/*' => Http::response([
                'statuscode'    => '025',
                'status'        => 'PAYMENT_PENDING',
                'RRR'           => 'RRR12345678',
                'transactionId' => 'TXN_001',
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
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
        Http::fake([
            'https://remitademo.net/*' => Http::response([
                'status' => 'SUCCESSFUL',
                'amount' => 5000,
                'RRR'    => 'RRR12345678',
            ], 200),
        ]);

        $response = $this->driver()->verifyPayment('RRR12345678');

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('SUCCESSFUL', $response['status']);
    }

    public function test_does_not_implement_subscription_interface(): void
    {
        $this->assertNotInstanceOf(
            \NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface::class,
            $this->driver()
        );
    }
}
