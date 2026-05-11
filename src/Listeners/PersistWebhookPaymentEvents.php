<?php

namespace NexusPay\PaymentMadeEasy\Listeners;

use NexusPay\PaymentMadeEasy\Events\ChargebackCreated;
use NexusPay\PaymentMadeEasy\Events\DisputeCreated;
use NexusPay\PaymentMadeEasy\Models\PaymentDispute;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\PaymentPending;
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCancelled;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCreated;
use NexusPay\PaymentMadeEasy\Events\SubscriptionRenewed;
use NexusPay\PaymentMadeEasy\Events\TransferFailed;
use NexusPay\PaymentMadeEasy\Events\TransferSuccessful;
use NexusPay\PaymentMadeEasy\Services\PaymentRecorder;

/**
 * When recording.auto_from_webhook_events is enabled, maps first-class webhook events to PaymentRecorder.
 */
class PersistWebhookPaymentEvents
{
    public function __construct(
        protected PaymentRecorder $recorder
    ) {
    }

    protected function shouldPersist(): bool
    {
        return config('payment-gateways.recording.enabled', false)
            && config('payment-gateways.recording.auto_from_webhook_events', false);
    }

    public function handlePaymentSuccessful(PaymentSuccessful $event): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $data = $event->paymentData;
        $ref = $this->transactionReference($data);
        if ($ref === null || $ref === '') {
            return;
        }
        $this->recorder->handleTransactionWebhook(
            $event->webhookEvent->getGateway(),
            $ref,
            'successful',
            (float) ($data['amount'] ?? 0),
            (string) ($data['currency'] ?? config('payment-gateways.currency', 'NGN')),
            $data['gateway_reference'] ?? $data['id'] ?? null,
            $event->webhookEvent->getRawPayload()
        );
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $data = $event->paymentData;
        $ref = $this->transactionReference($data);
        if ($ref === null || $ref === '') {
            return;
        }
        $this->recorder->handleTransactionWebhook(
            $event->webhookEvent->getGateway(),
            $ref,
            'failed',
            (float) ($data['amount'] ?? 0),
            (string) ($data['currency'] ?? config('payment-gateways.currency', 'NGN')),
            $data['gateway_reference'] ?? $data['id'] ?? null,
            $event->webhookEvent->getRawPayload()
        );
    }

    public function handlePaymentPending(PaymentPending $event): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $data = $event->paymentData;
        $ref = $this->transactionReference($data);
        if ($ref === null || $ref === '') {
            return;
        }
        $this->recorder->handleTransactionWebhook(
            $event->webhookEvent->getGateway(),
            $ref,
            'pending',
            (float) ($data['amount'] ?? 0),
            (string) ($data['currency'] ?? config('payment-gateways.currency', 'NGN')),
            $data['gateway_reference'] ?? $data['id'] ?? null,
            $event->webhookEvent->getRawPayload()
        );
    }

    public function handleRefundProcessed(RefundProcessed $event): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $data = $event->refundData;
        $ref = $this->refundTransactionReference($data);
        if ($ref === null || $ref === '') {
            return;
        }
        $this->recorder->handleRefundWebhook(
            $event->webhookEvent->getGateway(),
            $ref,
            isset($data['amount']) ? (float) $data['amount'] : null,
            isset($data['currency']) ? (string) $data['currency'] : null,
            $event->webhookEvent->getRawPayload(),
            $this->extractGatewayRefundIdFromRefundData($data)
        );
    }

    public function handleDisputeCreated(DisputeCreated $event): void
    {
        $this->persistDisputeOrChargeback(
            $event->webhookEvent->getGateway(),
            $event->disputeData,
            $event->webhookEvent->getRawPayload(),
            PaymentDispute::KIND_DISPUTE
        );
    }

    public function handleChargebackCreated(ChargebackCreated $event): void
    {
        $this->persistDisputeOrChargeback(
            $event->webhookEvent->getGateway(),
            $event->chargebackData,
            $event->webhookEvent->getRawPayload(),
            PaymentDispute::KIND_CHARGEBACK
        );
    }

    public function handleTransferSuccessful(TransferSuccessful $event): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $data = $event->transferData;
        $ref = (string) ($data['reference'] ?? $data['transfer_code'] ?? '');
        if ($ref === '') {
            return;
        }
        $this->recorder->handleTransferWebhook(
            $event->webhookEvent->getGateway(),
            $ref,
            'successful',
            $data['transfer_code'] ?? $data['gateway_reference'] ?? null,
            $event->webhookEvent->getRawPayload()
        );
    }

    public function handleTransferFailed(TransferFailed $event): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $data = $event->transferData;
        $ref = (string) ($data['reference'] ?? $data['transfer_code'] ?? '');
        if ($ref === '') {
            return;
        }
        $this->recorder->handleTransferWebhook(
            $event->webhookEvent->getGateway(),
            $ref,
            'failed',
            $data['transfer_code'] ?? null,
            $event->webhookEvent->getRawPayload()
        );
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->persistSubscription(
            $event->webhookEvent->getGateway(),
            $event->subscriptionData,
            $event->subscriptionData['status'] ?? 'active'
        );
    }

    public function handleSubscriptionRenewed(SubscriptionRenewed $event): void
    {
        $this->persistSubscription($event->webhookEvent->getGateway(), $event->subscriptionData, 'active');
    }

    public function handleSubscriptionCancelled(SubscriptionCancelled $event): void
    {
        $this->persistSubscription($event->webhookEvent->getGateway(), $event->subscriptionData, 'cancelled');
    }

    protected function persistSubscription(string $gateway, array $data, string $status): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $code = (string) ($data['subscription_code'] ?? '');
        if ($code === '') {
            return;
        }
        $this->recorder->handleSubscriptionWebhook($gateway, $code, $status, $data);
    }

    protected function transactionReference(array $data): ?string
    {
        $ref = $data['reference'] ?? $data['tx_ref'] ?? null;
        if ($ref !== null && $ref !== '') {
            return (string) $ref;
        }
        if (!empty($data['metadata']) && is_array($data['metadata']) && isset($data['metadata']['reference'])) {
            return (string) $data['metadata']['reference'];
        }
        if (!empty($data['checkout_request_id'])) {
            return (string) $data['checkout_request_id'];
        }

        return null;
    }

    protected function extractGatewayRefundIdFromRefundData(array $data): ?string
    {
        foreach (['id', 'refund_id', 'gateway_refund_id'] as $key) {
            if (!empty($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }

        return null;
    }

    protected function refundTransactionReference(array $data): ?string
    {
        foreach (['reference', 'transaction_reference'] as $key) {
            if (!empty($data[$key])) {
                return (string) $data[$key];
            }
        }
        if (!empty($data['transaction']['reference'])) {
            return (string) $data['transaction']['reference'];
        }

        return $this->transactionReference($data);
    }

    protected function persistDisputeOrChargeback(string $gateway, array $data, array $rawPayload, string $kind): void
    {
        if (!$this->shouldPersist()) {
            return;
        }
        $ref = $this->transactionReference($data);
        if ($ref === null || $ref === '') {
            return;
        }
        $this->recorder->handleDisputeWebhook(
            $gateway,
            $ref,
            $kind,
            $data,
            $rawPayload,
            $this->extractGatewayDisputeIdFromData($data)
        );
    }

    protected function extractGatewayDisputeIdFromData(array $data): ?string
    {
        foreach (['id', 'dispute_id', 'gateway_dispute_id'] as $key) {
            if (!empty($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }

        return null;
    }
}
