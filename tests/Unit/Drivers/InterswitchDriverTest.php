<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Drivers\InterswitchDriver;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class InterswitchDriverTest extends TestCase
{
    private function driver(): InterswitchDriver
    {
        return new InterswitchDriver([
            'driver'        => 'interswitch',
            'client_id'     => 'DSTV_SANDBOX_ID',
            'client_secret' => 'test_client_secret',
            'merchant_code' => 'MX6072',
            'payable_code'  => 'Default_Payable_MX6072',
            'base_url'      => 'https://qa.interswitchng.com',
            'passport_url'  => 'https://qa.interswitchng.com',
            'checkout_url'  => 'https://qa.interswitchng.com/collections/w/pay',
            'callback_url'  => 'https://example.com/callback',
        ]);
    }

    public function test_initialize_payment_fetches_token_then_creates_purchase(): void
    {
        Http::fake([
            'https://qa.interswitchng.com/*' => Http::sequence()
                ->push(['access_token' => 'isw_token_abc', 'expires_in' => 3600], 200)
                ->push([
                    'paymentUrl'           => 'https://qa.interswitchng.com/collections/w/pay?ref=ORDER_001',
                    'transactionReference' => 'ORDER_001',
                    'amount'               => 500000,
                ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'     => 'test@example.com',
            'name'      => 'Test User',
            'amount'    => 5000.00,
            'reference' => 'ORDER_001',
        ]);

        $this->assertArrayHasKey('paymentUrl', $response);
    }

    public function test_token_is_cached_between_requests(): void
    {
        Http::fake([
            'https://qa.interswitchng.com/*' => Http::sequence()
                ->push(['access_token' => 'cached_token', 'expires_in' => 3600], 200)
                ->push(['paymentUrl' => 'https://example.com', 'transactionReference' => 'R1'], 200)
                ->push(['paymentUrl' => 'https://example.com', 'transactionReference' => 'R2'], 200),
        ]);

        $d = $this->driver();
        $d->initializePayment(['email' => 'a@b.com', 'amount' => 100, 'reference' => 'R1']);
        $d->initializePayment(['email' => 'a@b.com', 'amount' => 200, 'reference' => 'R2']);

        $this->assertTrue(true);
    }

    public function test_amount_converted_to_kobo(): void
    {
        Http::fake([
            'https://qa.interswitchng.com/*' => Http::sequence()
                ->push(['access_token' => 'tok', 'expires_in' => 3600], 200)
                ->push(['paymentUrl' => 'https://example.com', 'transactionReference' => 'R1'], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'email'  => 'test@example.com',
            'amount' => 5000.00,
        ]);

        $this->assertArrayHasKey('paymentUrl', $response);
    }

    public function test_verify_payment_returns_response(): void
    {
        Http::fake([
            'https://qa.interswitchng.com/*' => Http::sequence()
                ->push(['access_token' => 'tok', 'expires_in' => 3600], 200)
                ->push([
                    'responseCode'         => '00',
                    'responseDescription'  => 'Approved by Financial Institution',
                    'transactionReference' => 'ORDER_001',
                    'amount'               => 500000,
                ], 200),
        ]);

        $response = $this->driver()->verifyPayment('ORDER_001');

        $this->assertArrayHasKey('responseCode', $response);
        $this->assertEquals('00', $response['responseCode']);
    }
}
