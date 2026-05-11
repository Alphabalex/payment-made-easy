<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\MonnifyDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MonnifyDriverTest extends TestCase
{
    private function driver(): MonnifyDriver
    {
        return new MonnifyDriver([
            'driver'        => 'monnify',
            'api_key'       => 'MK_TEST_xxx',
            'secret_key'    => 'test_secret',
            'contract_code' => '1234567890',
            'base_url'      => 'https://api.monnify.com',
            'callback_url'  => 'https://example.com/callback',
        ]);
    }

    public function test_implements_correct_interfaces(): void
    {
        $driver = $this->driver();
        $this->assertInstanceOf(SubscriptionDriverInterface::class, $driver);
        $this->assertInstanceOf(DisbursementDriverInterface::class, $driver);
        $this->assertInstanceOf(VirtualAccountDriverInterface::class, $driver);
    }

    public function test_initialize_payment_fetches_token_then_creates_transaction(): void
    {
        Http::fake([
            'https://api.monnify.com/*' => Http::sequence()
                ->push(['responseBody' => ['accessToken' => 'test_token_abc']], 200)
                ->push([
                    'responseBody' => [
                        'transactionReference' => 'MNFY|REF|001',
                        'checkoutUrl'          => 'https://checkout.monnify.com/pay/MNFY|REF|001',
                        'paymentReference'     => 'ORDER_001',
                    ],
                ], 200),
        ]);

        $response = $this->driver()->initializePayment([
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
        Http::fake([
            'https://api.monnify.com/*' => Http::sequence()
                ->push(['responseBody' => ['accessToken' => 'test_token']], 200)
                ->push([
                    'responseBody' => [
                        'paymentReference' => 'ORDER_001',
                        'paymentStatus'    => 'PAID',
                        'amountPaid'       => 5000,
                    ],
                ], 200),
        ]);

        $response = $this->driver()->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('responseBody', $response);
    }

    public function test_token_is_cached_between_calls(): void
    {
        Http::fake([
            'https://api.monnify.com/*' => Http::sequence()
                ->push(['responseBody' => ['accessToken' => 'cached_token']], 200)
                ->push([
                    'responseBody' => [
                        'transactionReference' => 'REF1',
                        'checkoutUrl'          => 'https://example.com',
                    ],
                ], 200)
                ->push(['responseBody' => ['paymentStatus' => 'PAID']], 200),
        ]);

        $d = $this->driver();
        $d->initializePayment([
            'email' => 'test@example.com', 'amount' => 1000, 'reference' => 'REF1',
        ]);
        $d->verifyPayment('REF1');

        $this->assertTrue(true);
    }
}
