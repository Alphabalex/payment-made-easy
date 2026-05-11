<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\SeerbitDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class SeerbitDriverTest extends TestCase
{
    private function driver(): SeerbitDriver
    {
        return new SeerbitDriver([
            'driver'       => 'seerbit',
            'public_key'   => 'SBPUBK_xxx',
            'secret_key'   => 'SBSECK_xxx',
            'base_url'     => 'https://seerbitapi.com/api/v2',
            'callback_url' => 'https://example.com/callback',
        ]);
    }

    public function test_implements_correct_interfaces(): void
    {
        $d = $this->driver();
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $d);
        $this->assertInstanceOf(VirtualAccountDriverInterface::class, $d);
    }

    public function test_initialize_payment_returns_response(): void
    {
        Http::fake([
            'https://seerbitapi.com/*' => Http::response([
                'status'  => 'SUCCESS',
                'message' => 'SUCCESS',
                'data'    => [
                    'payments' => [
                        'redirectLink'       => 'https://pay.seerbit.com/checkout/abc123',
                        'paymentReference' => 'ORDER_001',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'     => 'test@example.com',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('SUCCESS', $response['status']);
    }

    public function test_verify_payment_returns_response(): void
    {
        Http::fake([
            'https://seerbitapi.com/*' => Http::response([
                'status' => 'SUCCESS',
                'data'   => [
                    'payments' => [
                        'paymentReference'  => 'ORDER_001',
                        'transactionStatus' => 'SUCCESSFUL',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->driver()->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('status', $response);
    }

    public function test_does_not_implement_disbursement_interface(): void
    {
        $this->assertNotInstanceOf(
            \NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface::class,
            $this->driver()
        );
    }
}
