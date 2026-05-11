<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use NexusPay\PaymentMadeEasy\Contracts\WebhookLogSanitizerInterface;
use NexusPay\PaymentMadeEasy\Contracts\WebhookHandlerInterface;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\ChargebackCreated;
use NexusPay\PaymentMadeEasy\Events\DisputeCreated;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\PaymentPending;
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCancelled;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCreated;
use NexusPay\PaymentMadeEasy\Events\SubscriptionRenewed;
use NexusPay\PaymentMadeEasy\Events\TransferFailed;
use NexusPay\PaymentMadeEasy\Events\TransferSuccessful;
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
            $context = [
                'gateway'    => $event->getGateway(),
                'event_type' => $event->getEventType(),
            ];
            if (config('payment-gateways.webhooks.log_detail', 'full') === 'full') {
                $data = $event->getData();
                if (config('payment-gateways.webhooks.log_sanitize', true)) {
                    $data = App::make(WebhookLogSanitizerInterface::class)->sanitize($data);
                }
                $context['data'] = $data;
            }
            Log::info('Payment webhook received', $context);
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

            case 'payment.pending':
                Event::dispatch(new PaymentPending($event, $event->getData()));
                break;

            case 'refund.processed':
                Event::dispatch(new RefundProcessed($event, $event->getData()));
                break;

            case 'transfer.successful':
                Event::dispatch(new TransferSuccessful($event, $event->getData()));
                break;

            case 'transfer.failed':
                $reason = $this->extractFailureReason($event->getData());
                Event::dispatch(new TransferFailed($event, $event->getData(), $reason));
                break;

            case 'subscription.created':
                Event::dispatch(new SubscriptionCreated($event, $event->getData()));
                break;

            case 'subscription.cancelled':
                $reason = $this->extractFailureReason($event->getData());
                Event::dispatch(new SubscriptionCancelled($event, $event->getData(), $reason));
                break;

            case 'subscription.renewed':
                Event::dispatch(new SubscriptionRenewed($event, $event->getData()));
                break;

            case 'dispute.created':
                Event::dispatch(new DisputeCreated($event, $event->getData()));
                break;

            case 'chargeback.created':
                Event::dispatch(new ChargebackCreated($event, $event->getData()));
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

    /**
     * Gateways that verify via HMAC (or Stripe signing secret) must have non-empty signing material when
     * webhooks.verify_signature and webhooks.require_signing_secret are true. Handlers without such signing
     * (e.g. M-Pesa) override requiresConfiguredSigningSecret() to return false.
     */
    public function ensureSigningSecretConfiguredWhenRequired(): void
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return;
        }
        if (!(bool) config('payment-gateways.webhooks.require_signing_secret', true)) {
            return;
        }
        if (!$this->requiresConfiguredSigningSecret()) {
            return;
        }
        if ($this->configuredSigningSecret() !== null) {
            return;
        }

        throw new WebhookException(
            'Webhook signing secret is not configured for gateway [' . $this->gateway . ']. '
            . 'Set webhook_secret (or the gateway-specific secret documented for that driver) in config, '
            . 'or disable PAYMENT_WEBHOOK_REQUIRE_SIGNING_SECRET only if you accept weaker verification.'
        );
    }

    /**
     * @return bool False for gateways that do not use a configurable shared secret for webhook verification.
     */
    protected function requiresConfiguredSigningSecret(): bool
    {
        return true;
    }

    /**
     * Non-empty trimmed secret used when requiresConfiguredSigningSecret() is true.
     */
    protected function configuredSigningSecret(): ?string
    {
        $s = trim((string) ($this->config['webhook_secret'] ?? ''));

        return $s !== '' ? $s : null;
    }

    abstract protected function getSignatureFromRequest(Request $request): string;
    abstract protected function calculateExpectedSignature(string $payload): string;
}
