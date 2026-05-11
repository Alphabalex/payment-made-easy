<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NexusPay\PaymentMadeEasy\Models\PaymentDispute;
use NexusPay\PaymentMadeEasy\Models\PaymentTransaction;
use NexusPay\PaymentMadeEasy\Services\PaymentRecorder;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaymentRecorderDisputesTest extends TestCase
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

    public function test_handle_dispute_webhook_creates_row_and_marks_transaction_disputed(): void
    {
        PaymentTransaction::create([
            'gateway'   => 'paystack',
            'reference' => 'ORD_DISPUTE_1',
            'amount'    => 200.00,
            'currency'  => 'NGN',
            'status'    => 'successful',
        ]);

        $d = $this->recorder()->handleDisputeWebhook(
            'paystack',
            'ORD_DISPUTE_1',
            PaymentDispute::KIND_DISPUTE,
            ['amount' => 200, 'currency' => 'NGN', 'status' => 'open', 'id' => 'dsp_001'],
            ['event' => 'dispute.create'],
            'dsp_001'
        );

        $this->assertEquals(PaymentDispute::KIND_DISPUTE, $d->kind);
        $this->assertEquals('dsp_001', $d->gateway_dispute_id);
        $this->assertTrue(PaymentTransaction::where('reference', 'ORD_DISPUTE_1')->first()->isDisputed());
        $this->assertEquals(1, PaymentDispute::count());
    }

    public function test_duplicate_gateway_dispute_id_returns_existing(): void
    {
        PaymentTransaction::create([
            'gateway'   => 'stripe',
            'reference' => 'ORD_DUP_DSP',
            'amount'    => 50.00,
            'currency'  => 'USD',
            'status'    => 'successful',
        ]);

        $r = $this->recorder();
        $a = $r->handleDisputeWebhook('stripe', 'ORD_DUP_DSP', PaymentDispute::KIND_CHARGEBACK, ['id' => 'dp_1'], [], 'dp_1');
        $b = $r->handleDisputeWebhook('stripe', 'ORD_DUP_DSP', PaymentDispute::KIND_CHARGEBACK, ['id' => 'dp_1'], [], 'dp_1');

        $this->assertSame($a->id, $b->id);
        $this->assertEquals(1, PaymentDispute::count());
    }
}
