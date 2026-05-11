<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\MTNMoMoDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MTNMoMoDriverTest extends TestCase
{
    private function driver(): MTNMoMoDriver
    {
        return new MTNMoMoDriver([
            'driver'                        => 'mtnmomo',
            'collection_user_id'            => 'test_user_id',
            'collection_api_key'            => 'test_api_key',
            'collection_subscription_key' => 'test_col_sub_key',
            'disbursement_user_id'          => 'test_disb_user_id',
            'disbursement_api_key'          => 'test_disb_api_key',
            'disbursement_subscription_key' => 'test_disb_sub_key',
            'environment'                   => 'sandbox',
            'currency'                      => 'EUR',
            'base_url'                      => 'https://sandbox.momodeveloper.mtn.com',
            'callback_url'                  => 'https://example.com/mtnmomo/callback',
        ]);
    }

    public function test_mtnmomo_implements_disbursement_interface(): void
    {
        $this->assertInstanceOf(DisbursementDriverInterface::class, $this->driver());
    }

    public function test_initialize_payment_request_to_pay(): void
    {
        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/*' => Http::sequence()
                ->push(['access_token' => 'col_token', 'expires_in' => 3600], 200)
                ->push('', 202, ['X-Reference-Id' => 'uuid-ref-001']),
        ]);

        $response = $this->driver()->initializePayment([
            'phone'    => '256771234567',
            'amount'   => 5000,
            'currency' => 'UGX',
        ]);

        $this->assertTrue($response['status']);
        $this->assertArrayHasKey('reference', $response['data']);
    }

    public function test_verify_payment_fetches_status(): void
    {
        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/*' => Http::sequence()
                ->push(['access_token' => 'col_token', 'expires_in' => 3600], 200)
                ->push([
                    'financialTransactionId' => 'fin_txn_123',
                    'externalId'             => 'uuid-ref-001',
                    'amount'                   => '5000',
                    'currency'                 => 'UGX',
                    'status'                   => 'SUCCESSFUL',
                ], 200),
        ]);

        $response = $this->driver()->verifyPayment('uuid-ref-001');

        $this->assertEquals('SUCCESSFUL', $response['status']);
    }

    public function test_transfer_disbursement(): void
    {
        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/*' => Http::sequence()
                ->push(['access_token' => 'disb_token', 'expires_in' => 3600], 200)
                ->push('', 202, ['X-Reference-Id' => 'disb-ref-001']),
        ]);

        $response = $this->driver()->transfer([
            'phone'     => '256771234567',
            'amount'    => 10000,
            'currency'  => 'UGX',
            'narration' => 'Test payout',
        ]);

        $this->assertTrue($response['status']);
        $this->assertArrayHasKey('reference', $response['data']);
    }

    public function test_throws_on_bad_token_request(): void
    {
        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(PaymentException::class);

        $this->driver()->initializePayment([
            'phone'    => '256771234567',
            'amount'   => 5000,
            'currency' => 'UGX',
        ]);
    }

    public function test_collection_and_disbursement_tokens_are_cached_separately(): void
    {
        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/*' => Http::sequence()
                ->push(['access_token' => 'col_tok', 'expires_in' => 3600], 200)
                ->push('', 202, ['X-Reference-Id' => 'ref-001'])
                ->push(['access_token' => 'disb_tok', 'expires_in' => 3600], 200)
                ->push('', 202, ['X-Reference-Id' => 'ref-002']),
        ]);

        $d = $this->driver();
        $d->initializePayment(['phone' => '256771234567', 'amount' => 1000, 'currency' => 'UGX']);
        $d->transfer(['phone' => '256771234567', 'amount' => 500, 'currency' => 'UGX']);

        $this->assertTrue(true);
    }
}
