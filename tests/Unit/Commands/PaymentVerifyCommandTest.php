<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Commands;

use Mockery;
use NexusPay\PaymentMadeEasy\Drivers\PaystackDriver;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\PaymentManager;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaymentVerifyCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Unknown gateway
    // -------------------------------------------------------------------------

    public function test_unknown_gateway_returns_failure(): void
    {
        $this->artisan('payment:verify', [
            'gateway'   => 'unknown_xyz',
            'reference' => 'REF_001',
        ])
            ->expectsOutputToContain("Unknown gateway")
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // Successful verification
    // -------------------------------------------------------------------------

    public function test_successful_verification_shows_status_in_output(): void
    {
        $mockDriver = Mockery::mock(PaystackDriver::class);
        $mockDriver->shouldReceive('verifyPayment')
            ->once()
            ->with('REF_SUCCESS_001')
            ->andReturn([
                'status' => true,
                'data'   => [
                    'status'   => 'success',
                    'amount'   => 5000,
                    'currency' => 'NGN',
                    'customer' => ['email' => 'customer@example.com'],
                    'paid_at'  => '2026-05-07T10:00:00.000Z',
                ],
            ]);

        $this->mock(PaymentManager::class, function ($mock) use ($mockDriver) {
            $mock->shouldReceive('driver')
                ->andReturn($mockDriver);
        });

        $this->artisan('payment:verify', [
            'gateway'   => 'paystack',
            'reference' => 'REF_SUCCESS_001',
        ])
            ->expectsOutputToContain('success')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // --json flag
    // -------------------------------------------------------------------------

    public function test_json_flag_outputs_raw_response(): void
    {
        $rawResponse = [
            'status' => true,
            'data'   => [
                'reference' => 'REF_JSON_001',
                'status'    => 'success',
                'amount'    => 10000,
                'currency'  => 'NGN',
            ],
        ];

        $mockDriver = Mockery::mock(PaystackDriver::class);
        $mockDriver->shouldReceive('verifyPayment')
            ->once()
            ->andReturn($rawResponse);

        $this->mock(PaymentManager::class, function ($mock) use ($mockDriver) {
            $mock->shouldReceive('driver')->andReturn($mockDriver);
        });

        $this->artisan('payment:verify', [
            'gateway'   => 'paystack',
            'reference' => 'REF_JSON_001',
            '--json'    => true,
        ])
            ->expectsOutputToContain('"status"')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // PaymentException handling
    // -------------------------------------------------------------------------

    public function test_payment_exception_shows_error_and_returns_failure(): void
    {
        $mockDriver = Mockery::mock(PaystackDriver::class);
        $mockDriver->shouldReceive('verifyPayment')
            ->once()
            ->andThrow(new PaymentException('Transaction not found'));

        $this->mock(PaymentManager::class, function ($mock) use ($mockDriver) {
            $mock->shouldReceive('driver')->andReturn($mockDriver);
        });

        $this->artisan('payment:verify', [
            'gateway'   => 'paystack',
            'reference' => 'REF_MISSING',
        ])
            ->expectsOutputToContain('Transaction not found')
            ->assertFailed();
    }
}
