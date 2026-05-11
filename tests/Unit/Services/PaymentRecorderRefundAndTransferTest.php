<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NexusPay\PaymentMadeEasy\Models\PaymentTransaction;
use NexusPay\PaymentMadeEasy\Models\PaymentTransfer;
use NexusPay\PaymentMadeEasy\Services\PaymentRecorder;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaymentRecorderRefundAndTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    private function recorder(): PaymentRecorder
    {
        return $this->app->make(PaymentRecorder::class);
    }

    public function test_handle_refund_webhook_marks_transaction_refunded(): void
    {
        PaymentTransaction::create([
            'gateway'   => 'paystack',
            'reference' => 'TXN_REFUND_ME',
            'amount'    => 100.00,
            'currency'  => 'NGN',
            'status'    => 'successful',
        ]);

        $updated = $this->recorder()->handleRefundWebhook(
            'paystack',
            'TXN_REFUND_ME',
            100.0,
            'NGN',
            ['event' => 'refund.processed']
        );

        $this->assertTrue($updated->isRefunded());
        $this->assertArrayHasKey('refund', $updated->metadata ?? []);
        $this->assertCount(1, $updated->refunds);
        $this->assertEquals(100.0, (float) $updated->refunds->first()->amount);
    }

    public function test_multiple_partial_refunds_then_full(): void
    {
        PaymentTransaction::create([
            'gateway'   => 'paystack',
            'reference' => 'TXN_PARTIAL',
            'amount'    => 100.00,
            'currency'  => 'NGN',
            'status'    => 'successful',
        ]);

        $r = $this->recorder();
        $r->handleRefundWebhook('paystack', 'TXN_PARTIAL', 30.0, 'NGN', ['n' => 1], 'rfnd_30');
        $t = PaymentTransaction::where('reference', 'TXN_PARTIAL')->first();
        $this->assertTrue($t->isPartiallyRefunded());
        $this->assertEquals(1, $t->refunds()->count());

        $r->handleRefundWebhook('paystack', 'TXN_PARTIAL', 70.0, 'NGN', ['n' => 2], 'rfnd_70');
        $t->refresh();
        $this->assertTrue($t->isRefunded());
        $this->assertEquals(2, $t->refunds()->count());
        $this->assertEquals(100.0, $t->totalRefundedAmount());
    }

    public function test_duplicate_gateway_refund_id_is_ignored(): void
    {
        PaymentTransaction::create([
            'gateway'   => 'stripe',
            'reference' => 'ORD_DUP_REFUND',
            'amount'    => 50.00,
            'currency'  => 'USD',
            'status'    => 'successful',
        ]);

        $r = $this->recorder();
        $r->handleRefundWebhook('stripe', 'ORD_DUP_REFUND', 50.0, 'USD', ['a' => 1], 're_123');
        $r->handleRefundWebhook('stripe', 'ORD_DUP_REFUND', 50.0, 'USD', ['a' => 2], 're_123');

        $t = PaymentTransaction::where('reference', 'ORD_DUP_REFUND')->first();
        $this->assertEquals(1, $t->refunds()->count());
    }

    public function test_handle_transfer_webhook_does_not_match_other_gateway(): void
    {
        PaymentTransfer::create([
            'gateway'           => 'paystack',
            'reference'         => 'TRF_001',
            'gateway_reference' => null,
            'amount'            => 500.00,
            'currency'          => 'NGN',
            'status'              => 'pending',
        ]);

        $this->assertNull(
            $this->recorder()->handleTransferWebhook('flutterwave', 'TRF_001', 'successful')
        );

        $this->assertEquals(
            'pending',
            PaymentTransfer::where('gateway', 'paystack')->first()->status
        );
    }
}
