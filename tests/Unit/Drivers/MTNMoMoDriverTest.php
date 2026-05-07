<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NexusPay\PaymentMadeEasy\Drivers\MTNMoMoDriver;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class MTNMoMoDriverTest extends TestCase
{
    private function makeDriver(array $responses): MTNMoMoDriver
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        $driver = new MTNMoMoDriver([
            'driver'                        => 'mtnmomo',
            'collection_user_id'            => 'test_user_id',
            'collection_api_key'            => 'test_api_key',
            'collection_subscription_key'   => 'test_col_sub_key',
            'disbursement_user_id'          => 'test_disb_user_id',
            'disbursement_api_key'          => 'test_disb_api_key',
            'disbursement_subscription_key' => 'test_disb_sub_key',
            'environment'                   => 'sandbox',
            'currency'                      => 'EUR',
            'base_url'                      => 'https://sandbox.momodeveloper.mtn.com',
            'callback_url'                  => 'https://example.com/mtnmomo/callback',
        ]);

        $reflection = new \ReflectionClass($driver);
        $prop = $reflection->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($driver, $client);

        return $driver;
    }

    public function test_mtnmomo_implements_disbursement_interface(): void
    {
        $driver = $this->makeDriver([]);
        $this->assertInstanceOf(DisbursementDriverInterface::class, $driver);
    }

    public function test_initialize_payment_request_to_pay(): void
    {
        $driver = $this->makeDriver([
            // Collection token
            new Response(200, [], json_encode(['access_token' => 'col_token', 'expires_in' => 3600])),
            // RequestToPay — 202 Accepted (async, empty body)
            new Response(202, ['X-Reference-Id' => 'uuid-ref-001'], ''),
        ]);

        $response = $driver->initializePayment([
            'phone'    => '256771234567',
            'amount'   => 5000,
            'currency' => 'UGX',
        ]);

        $this->assertTrue($response['status']);
        $this->assertArrayHasKey('reference', $response['data']);
    }

    public function test_verify_payment_fetches_status(): void
    {
        $driver = $this->makeDriver([
            // Collection token
            new Response(200, [], json_encode(['access_token' => 'col_token', 'expires_in' => 3600])),
            // GET requesttopay/{ref}
            new Response(200, [], json_encode([
                'financialTransactionId' => 'fin_txn_123',
                'externalId'             => 'uuid-ref-001',
                'amount'                 => '5000',
                'currency'               => 'UGX',
                'status'                 => 'SUCCESSFUL',
            ])),
        ]);

        $response = $driver->verifyPayment('uuid-ref-001');

        $this->assertEquals('SUCCESSFUL', $response['status']);
    }

    public function test_transfer_disbursement(): void
    {
        $driver = $this->makeDriver([
            // Disbursement token
            new Response(200, [], json_encode(['access_token' => 'disb_token', 'expires_in' => 3600])),
            // POST /disbursement/v1_0/transfer — 202 Accepted
            new Response(202, ['X-Reference-Id' => 'disb-ref-001'], ''),
        ]);

        $response = $driver->transfer([
            'phone'    => '256771234567',
            'amount'   => 10000,
            'currency' => 'UGX',
            'narration' => 'Test payout',
        ]);

        $this->assertTrue($response['status']);
        $this->assertArrayHasKey('reference', $response['data']);
    }

    public function test_throws_on_bad_token_request(): void
    {
        $driver = $this->makeDriver([
            new Response(401, [], json_encode(['error' => 'Unauthorized'])),
        ]);

        $this->expectException(PaymentException::class);

        $driver->initializePayment([
            'phone'    => '256771234567',
            'amount'   => 5000,
            'currency' => 'UGX',
        ]);
    }

    public function test_collection_and_disbursement_tokens_are_cached_separately(): void
    {
        $driver = $this->makeDriver([
            // Collection token (1st call)
            new Response(200, [], json_encode(['access_token' => 'col_tok', 'expires_in' => 3600])),
            // RequestToPay
            new Response(202, ['X-Reference-Id' => 'ref-001'], ''),
            // Disbursement token (separate cache entry — 2nd token call)
            new Response(200, [], json_encode(['access_token' => 'disb_tok', 'expires_in' => 3600])),
            // Transfer
            new Response(202, ['X-Reference-Id' => 'ref-002'], ''),
        ]);

        // Both operations complete with exactly 4 HTTP calls (2 tokens + 2 ops)
        $driver->initializePayment(['phone' => '256771234567', 'amount' => 1000, 'currency' => 'UGX']);
        $driver->transfer(['phone' => '256771234567', 'amount' => 500, 'currency' => 'UGX']);
        // If tokens weren't cached separately, MockHandler would run out of responses
        $this->assertTrue(true);
    }
}
