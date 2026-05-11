<?php

namespace NexusPay\PaymentMadeEasy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Support\WebhookRequestFactory;
use NexusPay\PaymentMadeEasy\WebhookManager;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retries apply only to unexpected failures (e.g. DB errors in listeners).
     * {@see WebhookException} is caught and not rethrown — bad signatures and similar
     * client/configuration errors must not spin the queue.
     */
    public int $tries = 5;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $gateway,
        public string $rawContent,
        public array $headers = []
    ) {
        $this->onConnection(config('payment-gateways.webhooks.queue_connection', 'default'));
        $this->onQueue(config('payment-gateways.webhooks.queue_name', 'payment-webhooks'));
    }

    public function handle(WebhookManager $webhookManager): void
    {
        try {
            $request = WebhookRequestFactory::fromRawContent($this->rawContent, $this->headers);
            $webhookManager->handle($this->gateway, $request);
        } catch (WebhookException $e) {
            Log::error('Queued webhook rejected (not retrying)', [
                'gateway' => $this->gateway,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
