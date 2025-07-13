<?php

namespace Kudos\PaymentMadeEasy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kudos\PaymentMadeEasy\WebhookManager;
use Kudos\PaymentMadeEasy\Exceptions\WebhookException;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $gateway;
    public array $payload;
    public array $headers;

    public function __construct(string $gateway, array $payload, array $headers)
    {
        $this->gateway = $gateway;
        $this->payload = $payload;
        $this->headers = $headers;
        
        $this->onConnection(config('payment-gateways.webhooks.queue_connection', 'default'));
        $this->onQueue(config('payment-gateways.webhooks.queue_name', 'payment-webhooks'));
    }

    public function handle(WebhookManager $webhookManager): void
    {
        try {
            // Create a mock request from the stored data
            $request = new \Illuminate\Http\Request();
            $request->replace($this->payload);
            
            foreach ($this->headers as $key => $value) {
                $request->headers->set($key, $value);
            }

            $webhookManager->handle($this->gateway, $request);
        } catch (WebhookException $e) {
            Log::error('Queued webhook processing failed', [
                'gateway' => $this->gateway,
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);

            throw $e;
        }
    }
}