<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use NexusPay\PaymentMadeEasy\Contracts\WebhookHandlerInterface;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

abstract class AbstractWebhookHandler implements WebhookHandlerInterface
{
    protected array $config;
    protected string $gateway;

    public function __construct(array $config, string $gateway)
    {
        $this->config = $config;
        $this->gateway = $gateway;
    }

    public function handle(WebhookEventInterface $event): void
    {
        if (!$event->isValid()) {
            throw new WebhookException('Invalid webhook event received');
        }

        if (config('payment-gateways.webhooks.log_events', true)) {
            Log::info('Payment webhook received', [
                'gateway' => $event->getGateway(),
                'event_type' => $event->getEventType(),
                'data' => $event->getData(),
            ]);
        }

        $this->dispatchEvent($event);
    }

    protected function dispatchEvent(WebhookEventInterface $event): void
    {
        $eventType = $this->mapEventType($event->getEventType());

        switch ($eventType) {
            case 'payment.successful':
                Event::dispatch(new PaymentSuccessful($event, $event->getData()));
                break;

            case 'payment.failed':
                $reason = $this->extractFailureReason($event->getData());
                Event::dispatch(new PaymentFailed($event, $event->getData(), $reason));
                break;

            case 'refund.processed':
                Event::dispatch(new RefundProcessed($event, $event->getData()));
                break;

            default:
                // Dispatch a generic webhook event
                Event::dispatch('payment.webhook.' . $eventType, [$event]);
                break;
        }
    }

    protected function mapEventType(string $originalEventType): string
    {
        $mapping = config("payment-gateways.webhooks.event_mapping.{$this->gateway}", []);

        return $mapping[$originalEventType] ?? $originalEventType;
    }

    protected function extractFailureReason(array $data): string
    {
        // Override in specific handlers to extract failure reason
        return $data['message'] ?? $data['reason'] ?? 'Payment failed';
    }

    abstract protected function getSignatureFromRequest(Request $request): string;
    abstract protected function calculateExpectedSignature(string $payload): string;
}
