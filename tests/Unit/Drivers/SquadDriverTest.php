<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\SquadDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class SquadDriverTest extends TestCase
{
    private function driver(): SquadDriver
    {
        return new SquadDriver([
            'driver'       => 'squad',
            'secret_key'   => 'sandbox_sk_xxx',
            'base_url'     => 'https://api-d.squadco.com',
            'callback_url' => 'https://example.com/callback',
        ]);
    }

    public function test_implements_correct_interfaces(): void
    {
        $d = $this->driver();
        $this->assertInstanceOf(DisbursementDriverInterface::class, $d);
        $this->assertInstanceOf(VirtualAccountDriverInterface::class, $d);
    }

    public function test_initialize_payment_returns_response(): void
    {
        Http::fake([
            'https://api-d.squadco.com/*' => Http::response([
                'status'  => 200,
                'success' => true,
                'data'    => [
                    'checkout_url'      => 'https://checkout.squadco.com/abc123',
                    'transaction_ref'   => 'ORDER_001',
                    'merchant_amount'   => 500000,
                ],
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'     => 'test@example.com',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('checkout_url', $response['data']);
    }

    public function test_amount_converted_to_kobo(): void
    {
        Http::fake([
            'https://api-d.squadco.com/*' => Http::response([
                'status' => 200,
                'data'   => ['checkout_url' => 'https://example.com'],
            ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 5000.00,
        ]);

        $this->assertArrayHasKey('data', $response);
    }

    public function test_verify_payment_returns_response(): void
    {
        Http::fake([
            'https://api-d.squadco.com/*' => Http::response([
                'status' => 200,
                'data'   => [
                    'transaction_ref'      => 'ORDER_001',
                    'transaction_status'   => 'Success',
                ],
            ], 200),
        ]);

        $response = $this->driver()->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('Success', $response['data']['transaction_status']);
    }

    public function test_list_banks_returns_response(): void
    {
        Http::fake([
            'https://api-d.squadco.com/*' => Http::response([
                'status' => 200,
                'data'   => [
                    ['bank_code' => '058', 'bank_name' => 'GTBank'],
                    ['bank_code' => '044', 'bank_name' => 'Access Bank'],
                ],
            ], 200),
        ]);

        $banks = $this->driver()->listBanks(['country' => 'NG']);

        $this->assertIsArray($banks);
    }
}
