<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Models\PaymentSubscription;
use NexusPay\PaymentMadeEasy\Models\PaymentTransaction;
use NexusPay\PaymentMadeEasy\Models\PaymentWebhookLog;
use NexusPay\PaymentMadeEasy\Services\PaymentRecorder;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaymentRecorderTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    public function test_record_transaction_creates_database_record(): void
    {
        $recorder = $this->recorder();

        $data = [
            'email'       => 'buyer@example.com',
            'amount'      => 5000.00,
            'currency'    => 'NGN',
            'reference'   => 'TXN_001',
            'description' => 'Test payment',
        ];

        $response = [
            'status' => true,
            'data'   => ['reference' => 'TXN_001', 'id' => 'gateway_txn_001'],
        ];

        $transaction = $recorder->recordTransaction('paystack', $data, $response);

        $this->assertInstanceOf(PaymentTransaction::class, $transaction);
        $this->assertEquals('paystack', $transaction->gateway);
        $this->assertEquals('TXN_001', $transaction->reference);
        $this->assertEquals('buyer@example.com', $transaction->email);
        $this->assertEquals(5000.00, $transaction->amount);
        $this->assertEquals('NGN', $transaction->currency);
        $this->assertEquals('pending', $transaction->status);
        $this->assertDatabaseHas('payment_transactions', ['reference' => 'TXN_001']);
    }

    public function test_update_transaction_status_updates_existing_record(): void
    {
        $recorder = $this->recorder();

        PaymentTransaction::create([
            'gateway'   => 'paystack',
            'reference' => 'TXN_UPDATE',
            'amount'    => 10000.00,
            'currency'  => 'NGN',
            'status'    => 'pending',
        ]);

        $result = $recorder->updateTransactionStatus('TXN_UPDATE', 'successful');

        $this->assertTrue($result);
        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'TXN_UPDATE',
            'status'    => 'successful',
        ]);
        // paid_at should be set for successful status
        $updated = PaymentTransaction::where('reference', 'TXN_UPDATE')->first();
        $this->assertNotNull($updated->paid_at);
    }

    public function test_update_transaction_status_returns_false_when_not_found(): void
    {
        $recorder = $this->recorder();

        $result = $recorder->updateTransactionStatus('REF_DOES_NOT_EXIST', 'successful');

        $this->assertFalse($result);
    }

    public function test_handle_transaction_webhook_creates_record_if_not_exists(): void
    {
        $recorder = $this->recorder();

        $transaction = $recorder->handleTransactionWebhook(
            gateway: 'paystack',
            reference: 'WEBHOOK_TXN_001',
            status: 'successful',
            amount: 7500.00,
            currency: 'NGN',
            gatewayReference: 'gw_ref_001'
        );

        $this->assertInstanceOf(PaymentTransaction::class, $transaction);
        $this->assertEquals('paystack', $transaction->gateway);
        $this->assertEquals('WEBHOOK_TXN_001', $transaction->reference);
        $this->assertEquals('successful', $transaction->status);
        $this->assertEquals(7500.00, $transaction->amount);
        $this->assertNotNull($transaction->paid_at);
        $this->assertDatabaseHas('payment_transactions', ['reference' => 'WEBHOOK_TXN_001']);
    }

    public function test_handle_transaction_webhook_updates_existing_record(): void
    {
        $recorder = $this->recorder();

        PaymentTransaction::create([
            'gateway'   => 'stripe',
            'reference' => 'STRIPE_001',
            'amount'    => 2000.00,
            'currency'  => 'USD',
            'status'    => 'pending',
        ]);

        $recorder->handleTransactionWebhook('stripe', 'STRIPE_001', 'successful', 2000.00, 'USD');

        $updated = PaymentTransaction::where('reference', 'STRIPE_001')->first();
        $this->assertEquals('successful', $updated->status);
        $this->assertNotNull($updated->paid_at);
    }

    // -------------------------------------------------------------------------
    // Subscriptions
    // -------------------------------------------------------------------------

    public function test_handle_subscription_webhook_does_not_match_other_gateway(): void
    {
        $recorder = $this->recorder();

        PaymentSubscription::create([
            'gateway'            => 'paystack',
            'plan_code'          => 'PLN_shared',
            'plan_name'          => 'Plan',
            'subscription_code'  => 'SUB_paystack_only',
            'email'              => 'a@example.com',
            'amount'             => 1000,
            'currency'           => 'NGN',
            'interval'           => 'monthly',
            'status'             => 'active',
            'invoice_limit'      => 0,
            'invoices_paid'      => 0,
        ]);

        $this->assertNull(
            $recorder->handleSubscriptionWebhook('stripe', 'SUB_paystack_only', 'cancelled')
        );

        $this->assertEquals(
            'active',
            PaymentSubscription::where('gateway', 'paystack')->first()->status
        );
    }

    public function test_record_subscription_creates_database_record(): void
    {
        $recorder = $this->recorder();

        $data = [
            'email'     => 'subscriber@example.com',
            'amount'    => 5000.00,
            'currency'  => 'NGN',
            'interval'  => 'monthly',
            'plan_code' => 'PLN_test_001',
        ];

        $response = [
            'status' => true,
            'data'   => ['subscription_code' => 'SUB_test_001'],
        ];

        $subscription = $recorder->recordSubscription('paystack', $data, $response);

        $this->assertInstanceOf(PaymentSubscription::class, $subscription);
        $this->assertEquals('paystack', $subscription->gateway);
        $this->assertEquals('subscriber@example.com', $subscription->email);
        $this->assertEquals('PLN_test_001', $subscription->plan_code);
        $this->assertEquals('SUB_test_001', $subscription->subscription_code);
        $this->assertEquals('monthly', $subscription->interval);
        $this->assertEquals('pending', $subscription->status);
        $this->assertDatabaseHas('payment_subscriptions', ['subscription_code' => 'SUB_test_001']);
    }

    // -------------------------------------------------------------------------
    // Webhook logs
    // -------------------------------------------------------------------------

    public function test_log_webhook_event_creates_log_record(): void
    {
        $recorder = $this->recorder();

        $event = new BaseWebhookEvent(
            'charge.success',
            ['reference' => 'LOG_REF_001', 'amount' => 3000],
            'paystack',
            ['event' => 'charge.success', 'data' => ['reference' => 'LOG_REF_001']]
        );

        $log = $recorder->logWebhookEvent('paystack', $event, 'processed');

        $this->assertInstanceOf(PaymentWebhookLog::class, $log);
        $this->assertEquals('paystack', $log->gateway);
        $this->assertEquals('charge.success', $log->event_type);
        $this->assertEquals('LOG_REF_001', $log->reference);
        $this->assertEquals('processed', $log->status);
        $this->assertDatabaseHas('payment_webhook_logs', [
            'gateway'    => 'paystack',
            'event_type' => 'charge.success',
            'reference'  => 'LOG_REF_001',
        ]);
    }
}
