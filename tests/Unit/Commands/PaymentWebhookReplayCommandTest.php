<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use NexusPay\PaymentMadeEasy\Models\PaymentWebhookLog;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaymentWebhookReplayCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    // -------------------------------------------------------------------------
    // Recording disabled
    // -------------------------------------------------------------------------

    public function test_returns_failure_when_recording_disabled(): void
    {
        $this->app['config']->set('payment-gateways.recording.enabled', false);

        $this->artisan('payment:webhook-replay', ['id' => 1])
            ->expectsOutputToContain('Database recording is disabled')
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // Log not found
    // -------------------------------------------------------------------------

    public function test_returns_failure_when_log_not_found(): void
    {
        $this->app['config']->set('payment-gateways.recording.enabled', true);

        $this->artisan('payment:webhook-replay', ['id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // Dry-run
    // -------------------------------------------------------------------------

    public function test_dry_run_prints_payload_without_dispatching(): void
    {
        $this->app['config']->set('payment-gateways.recording.enabled', true);

        $log = PaymentWebhookLog::create([
            'gateway'    => 'paystack',
            'event_type' => 'charge.success',
            'status'     => 'received',
            'payload'    => [
                'event' => 'charge.success',
                'data'  => ['reference' => 'ORDER_DRY_001'],
            ],
        ]);

        $this->artisan('payment:webhook-replay', [
            'id'        => $log->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        // Status must NOT have been updated (dry-run should not persist)
        $this->assertEquals('received', $log->fresh()->status);
    }
}
