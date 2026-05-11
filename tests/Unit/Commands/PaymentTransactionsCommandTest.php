<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NexusPay\PaymentMadeEasy\Models\PaymentTransaction;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaymentTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    public function test_command_lists_transactions(): void
    {
        PaymentTransaction::create([
            'gateway'   => 'paystack',
            'reference' => 'CLI_TXN_1',
            'amount'    => 10.00,
            'currency'  => 'NGN',
            'status'    => 'successful',
        ]);

        $this->artisan('payment:transactions', ['--gateway' => 'paystack', '--status' => 'successful', '--limit' => 5])
            ->assertSuccessful()
            ->expectsOutputToContain('CLI_TXN_1');
    }
}
