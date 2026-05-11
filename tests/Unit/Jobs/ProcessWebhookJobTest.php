<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Event;
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Jobs\ProcessWebhookJob;
use NexusPay\PaymentMadeEasy\Tests\TestCase;

class ProcessWebhookJobTest extends TestCase
{
    public function test_job_rebuilds_request_so_signature_verifies(): void
    {
        Event::fake();

        $secret = 'paystack_webhook_secret';
        $this->app['config']->set('payment-gateways.gateways.paystack.webhook_secret', $secret);

        $payload = json_encode([
            'event' => 'charge.success',
            'data'  => [
                'reference' => 'JOB_REF_001',
                'amount'    => 100000,
                'currency'  => 'NGN',
                'status'    => 'success',
                'customer'  => ['email' => 'job@example.com'],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $secret);

        $job = new ProcessWebhookJob('paystack', $payload, [
            'x-paystack-signature' => $signature,
            'content-type'         => 'application/json',
        ]);

        $job->handle($this->app->make(\NexusPay\PaymentMadeEasy\WebhookManager::class));

        Event::assertDispatched(PaymentSuccessful::class);
    }
}
