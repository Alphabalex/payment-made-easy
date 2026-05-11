<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\PaddleDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaddleDriverTest extends TestCase
{
    private function driver(): PaddleDriver
    {
        return new PaddleDriver([
            'driver'       => 'paddle',
            'api_key'      => 'pdl_snd_xxx',
            'base_url'     => 'https://sandbox-api.paddle.com',
            'callback_url' => 'https://example.com/callback',
        ]);
    }

    public function test_implements_correct_interfaces(): void
    {
        $d = $this->driver();
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $d);
        $this->assertInstanceOf(PaymentLinkDriverInterface::class, $d);
    }

    public function test_does_not_implement_disbursement_interface(): void
    {
        $this->assertNotInstanceOf(
            \NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface::class,
            $this->driver()
        );
    }

    public function test_initialize_payment_creates_transaction(): void
    {
        Http::fake([
            'https://sandbox-api.paddle.com/*' => Http::response([
                'data' => [
                    'id'      => 'txn_01abc',
                    'status'  => 'ready',
                    'checkout' => [
                        'url' => 'https://checkout.paddle.com/checkout/txn_01abc',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'currency'  => 'USD',
            'reference' => 'ORDER_001',
            'items'     => [['price_id' => 'pri_abc', 'quantity' => 1]],
        ]);

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('txn_01abc', $response['data']['id']);
    }

    public function test_verify_payment_returns_transaction(): void
    {
        Http::fake([
            'https://sandbox-api.paddle.com/*' => Http::response([
                'data' => [
                    'id'     => 'txn_01abc',
                    'status' => 'completed',
                    'details' => [
                        'totals' => ['grand_total' => '2999', 'currency_code' => 'USD'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->driver()->verifyPayment('txn_01abc');

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('completed', $response['data']['status']);
    }
}
