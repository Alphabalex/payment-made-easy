<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Drivers\MPesaDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MPesaDriverTest extends TestCase
{
    private function driver(): MPesaDriver
    {
        return new MPesaDriver([
            'driver'              => 'mpesa',
            'consumer_key'        => 'test_consumer_key',
            'consumer_secret'     => 'test_consumer_secret',
            'shortcode'           => '174379',
            'passkey'             => 'test_passkey',
            'initiator_name'      => 'testapi',
            'security_credential' => 'enc_credential',
            'base_url'            => 'https://sandbox.safaricom.co.ke',
            'callback_url'        => 'https://example.com/mpesa/callback',
            'result_url'          => 'https://example.com/mpesa/result',
            'timeout_url'         => 'https://example.com/mpesa/timeout',
        ]);
    }

    public function test_mpesa_does_not_implement_disbursement_interface(): void
    {
        $this->assertNotInstanceOf(DisbursementDriverInterface::class, $this->driver());
    }

    public function test_initialize_payment_stk_push(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/*' => Http::sequence()
                ->push(['access_token' => 'test_token_123', 'expires_in' => '3599'], 200)
                ->push([
                    'MerchantRequestID'   => 'MR-001',
                    'CheckoutRequestID'   => 'ws_CO_123456789',
                    'ResponseCode'        => '0',
                    'ResponseDescription' => 'Success. Request accepted for processing',
                    'CustomerMessage'     => 'Success. Request accepted for processing',
                ], 200),
        ]);

        $response = $this->driver()->initializePayment([
            'phone'  => '254712345678',
            'amount' => 1000,
        ]);

        $this->assertArrayHasKey('CheckoutRequestID', $response);
        $this->assertEquals('ws_CO_123456789', $response['CheckoutRequestID']);
    }

    public function test_verify_payment_queries_stk_status(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/*' => Http::sequence()
                ->push(['access_token' => 'tok', 'expires_in' => '3599'], 200)
                ->push([
                    'ResponseCode'        => '0',
                    'ResponseDescription' => 'The service request has been accepted successfully.',
                    'MerchantRequestID'   => 'MR-001',
                    'CheckoutRequestID'   => 'ws_CO_123456789',
                    'ResultCode'          => '0',
                    'ResultDesc'          => 'The service request is processed successfully.',
                ], 200),
        ]);

        $response = $this->driver()->verifyPayment('ws_CO_123456789');

        $this->assertEquals('0', $response['ResultCode']);
    }

    public function test_throws_on_http_error(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/*' => Http::sequence()
                ->push(['access_token' => 'tok', 'expires_in' => '3599'], 200)
                ->push(['errorCode' => '400.002.02', 'errorMessage' => 'Bad Request'], 400),
        ]);

        $this->expectException(PaymentException::class);

        $this->driver()->initializePayment([
            'phone'  => '254712345678',
            'amount' => 1000,
        ]);
    }

    public function test_token_is_cached_between_requests(): void
    {
        Http::fake([
            'https://sandbox.safaricom.co.ke/*' => Http::sequence()
                ->push(['access_token' => 'cached_token', 'expires_in' => '3599'], 200)
                ->push([
                    'CheckoutRequestID' => 'req_1',
                    'ResponseCode'      => '0',
                    'MerchantRequestID' => 'MR-1',
                    'ResponseDescription' => 'ok',
                    'CustomerMessage'   => 'ok',
                ], 200)
                ->push([
                    'ResponseCode'        => '0',
                    'ResponseDescription' => 'ok',
                    'MerchantRequestID'   => 'MR-2',
                    'CheckoutRequestID'   => 'req_1',
                    'ResultCode'          => '0',
                    'ResultDesc'          => 'ok',
                ], 200),
        ]);

        $d = $this->driver();
        $d->initializePayment(['phone' => '254712345678', 'amount' => 100]);
        $d->verifyPayment('req_1');

        $this->assertTrue(true);
    }
}
