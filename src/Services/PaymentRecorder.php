<?php

namespace NexusPay\PaymentMadeEasy\Services;

use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Models\PaymentDispute;
use NexusPay\PaymentMadeEasy\Models\PaymentRefund;
use NexusPay\PaymentMadeEasy\Models\PaymentSubscription;
use NexusPay\PaymentMadeEasy\Models\PaymentTransaction;
use NexusPay\PaymentMadeEasy\Models\PaymentTransfer;
use NexusPay\PaymentMadeEasy\Models\PaymentWebhookLog;

/**
 * PaymentRecorder
 *
 * Opt-in service that persists payment activity to the database.
 * Enable with `recording.enabled` in config (`PAYMENT_RECORDING_ENABLED`) or set
 * `recording.auto_from_webhook_events` to persist common outcomes from webhook events.
 *
 * Usage (direct):
 *   app(PaymentRecorder::class)->recordTransaction('paystack', $initData, $gatewayResponse);
 *
 * Or via events: listen to PaymentSuccessful / TransferSuccessful etc. and call the recorder.
 *
 * Refunds are stored in `payment_refunds` (see migration `2024_01_01_000005_create_payment_refunds_table`).
 * Disputes and chargebacks are stored in `payment_disputes` (migration `2024_01_01_000006_create_payment_disputes_table`).
 */
class PaymentRecorder
{
    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    /**
     * Create a new pending transaction record.
     */
    public function recordTransaction(string $gateway, array $data, array $response): PaymentTransaction
    {
        return PaymentTransaction::create([
            'gateway'           => $gateway,
            'reference'         => $data['reference'] ?? ($response['data']['reference'] ?? null),
            'gateway_reference' => $response['data']['id'] ?? ($response['data']['gateway_reference'] ?? null),
            'amount'            => $data['amount'] ?? 0,
            'currency'          => $data['currency'] ?? config('payment-gateways.currency', 'NGN'),
            'status'            => 'pending',
            'email'             => $data['email'] ?? null,
            'customer_name'     => $data['name'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'description'       => $data['description'] ?? null,
            'callback_url'      => $data['callback_url'] ?? null,
            'metadata'          => $data['metadata'] ?? null,
            'raw_response'      => $response,
        ]);
    }

    /**
     * Update an existing transaction's status after verification.
     */
    public function updateTransactionStatus(
        string $reference,
        string $status,
        string $gatewayReference = null,
        array $rawResponse = []
    ): bool {
        $transaction = PaymentTransaction::where('reference', $reference)->first();

        if (!$transaction) {
            return false;
        }

        $updates = ['status' => $status];

        if ($gatewayReference) {
            $updates['gateway_reference'] = $gatewayReference;
        }

        if (!empty($rawResponse)) {
            $updates['raw_response'] = $rawResponse;
        }

        if ($status === 'successful') {
            $updates['paid_at'] = now();
        }

        return $transaction->update($updates);
    }

    /**
     * Find or create a transaction, then mark it successful — called from webhook handler.
     */
    public function handleTransactionWebhook(
        string $gateway,
        string $reference,
        string $status,
        float $amount,
        string $currency,
        string $gatewayReference = null,
        array $rawPayload = []
    ): PaymentTransaction {
        $transaction = PaymentTransaction::where('reference', $reference)->first();

        if (!$transaction) {
            $transaction = PaymentTransaction::create([
                'gateway'           => $gateway,
                'reference'         => $reference,
                'gateway_reference' => $gatewayReference,
                'amount'            => $amount,
                'currency'          => $currency,
                'status'            => $status,
                'raw_response'      => $rawPayload,
                'paid_at'           => $status === 'successful' ? now() : null,
            ]);
        } else {
            $transaction->update([
                'status'            => $status,
                'gateway_reference' => $gatewayReference ?? $transaction->gateway_reference,
                'raw_response'      => $rawPayload ?: $transaction->raw_response,
                'paid_at'           => $status === 'successful' ? now() : $transaction->paid_at,
            ]);
        }

        return $transaction;
    }

    /**
     * Record a refund row on {@see PaymentRefund}, update parent {@see PaymentTransaction} status
     * (refunded vs partially_refunded), and merge a small snapshot into transaction metadata.
     *
     * Duplicate gateway refund IDs (when provided) are ignored.
     *
     * @param  string|null  $gatewayRefundId  Provider refund id for deduplication (optional if detectable from raw payload)
     */
    public function handleRefundWebhook(
        string $gateway,
        string $reference,
        ?float $amount = null,
        ?string $currency = null,
        array $rawPayload = [],
        ?string $gatewayRefundId = null
    ): PaymentTransaction {
        $refundKey = $gatewayRefundId ?? $this->detectGatewayRefundId($rawPayload);
        $refundAmount = (float) ($amount ?? 0);
        $refundCurrency = $currency ?? config('payment-gateways.currency', 'NGN');

        $transaction = PaymentTransaction::query()
            ->where('gateway', $gateway)
            ->where('reference', $reference)
            ->first();

        if (!$transaction) {
            $transaction = PaymentTransaction::create([
                'gateway'           => $gateway,
                'reference'         => $reference,
                'gateway_reference' => null,
                'amount'            => $refundAmount,
                'currency'          => $refundCurrency,
                'status'            => 'refunded',
                'metadata'          => $this->refundMetadataSnapshot($refundAmount, $refundCurrency, $refundAmount),
                'raw_response'      => $rawPayload,
            ]);
            $this->insertPaymentRefund(
                $transaction,
                $gateway,
                $reference,
                $refundKey,
                $refundAmount,
                $refundCurrency,
                $rawPayload
            );

            return $transaction->fresh(['refunds']);
        }

        if ($refundKey !== null && $refundKey !== '') {
            if ($transaction->refunds()->where('gateway_refund_id', $refundKey)->exists()) {
                return $transaction->fresh(['refunds']);
            }
        }

        $this->insertPaymentRefund(
            $transaction,
            $gateway,
            $reference,
            $refundKey,
            $refundAmount,
            $refundCurrency,
            $rawPayload
        );

        $transaction->load('refunds');
        $totalRefunded = (float) $transaction->refunds()->sum('amount');
        $originalAmount = (float) $transaction->amount;

        $mergedMeta = array_merge(
            $transaction->metadata ?? [],
            $this->refundMetadataSnapshot($refundAmount, $refundCurrency, $totalRefunded)
        );

        $nextStatus = $this->resolveRefundStatus($transaction->status, $totalRefunded, $originalAmount);

        $transaction->update([
            'status'       => $nextStatus,
            'metadata'     => $mergedMeta,
            'raw_response' => $rawPayload ?: $transaction->raw_response,
        ]);

        return $transaction->fresh(['refunds']);
    }

    protected function insertPaymentRefund(
        PaymentTransaction $transaction,
        string $gateway,
        string $reference,
        ?string $gatewayRefundId,
        float $amount,
        string $currency,
        array $rawPayload
    ): PaymentRefund {
        return PaymentRefund::create([
            'payment_transaction_id' => $transaction->id,
            'gateway'                => $gateway,
            'reference'              => $reference,
            'gateway_refund_id'      => $gatewayRefundId,
            'amount'                 => $amount,
            'currency'               => $currency,
            'raw_payload'            => $rawPayload ?: null,
        ]);
    }

    protected function refundMetadataSnapshot(float $lastAmount, string $currency, float $totalRefunded): array
    {
        return [
            'refund' => array_filter([
                'recorded_at'    => now()->toIso8601String(),
                'amount'         => $lastAmount,
                'currency'       => $currency,
                'total_refunded' => $totalRefunded,
            ], fn ($v) => $v !== null && $v !== '' && $v !== 0.0),
        ];
    }

    protected function resolveRefundStatus(string $currentStatus, float $totalRefunded, float $originalAmount): string
    {
        if ($totalRefunded <= 0) {
            return $currentStatus;
        }

        if ($this->refundTotalsCoverOriginal($totalRefunded, $originalAmount)) {
            return 'refunded';
        }

        if (in_array($currentStatus, ['successful', 'partially_refunded', 'refunded', 'pending'], true)) {
            return 'partially_refunded';
        }

        return $currentStatus;
    }

    protected function refundTotalsCoverOriginal(float $totalRefunded, float $originalAmount): bool
    {
        if ($originalAmount <= 0) {
            return $totalRefunded > 0;
        }

        return $totalRefunded + 0.004 >= $originalAmount;
    }

    protected function detectGatewayRefundId(array $rawPayload): ?string
    {
        foreach (['id', 'refund_id', 'gateway_refund_id'] as $key) {
            if (!empty($rawPayload[$key]) && is_scalar($rawPayload[$key])) {
                return (string) $rawPayload[$key];
            }
        }

        if (!empty($rawPayload['data']['id']) && is_scalar($rawPayload['data']['id'])) {
            return (string) $rawPayload['data']['id'];
        }

        $entityId = data_get($rawPayload, 'payload.refund.entity.id');
        if ($entityId !== null && is_scalar($entityId)) {
            return (string) $entityId;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Disputes & chargebacks
    // -------------------------------------------------------------------------

    /**
     * Persist a dispute or chargeback row and mark the linked transaction disputed when it exists.
     *
     * @param  string  $kind  {@see PaymentDispute::KIND_DISPUTE} or {@see PaymentDispute::KIND_CHARGEBACK}
     */
    public function handleDisputeWebhook(
        string $gateway,
        string $reference,
        string $kind,
        array $disputeData,
        array $rawPayload = [],
        ?string $gatewayDisputeId = null
    ): PaymentDispute {
        $kind = strtolower($kind);
        if (!in_array($kind, [PaymentDispute::KIND_DISPUTE, PaymentDispute::KIND_CHARGEBACK], true)) {
            $kind = PaymentDispute::KIND_DISPUTE;
        }

        $disputeKey = $gatewayDisputeId ?? $this->detectGatewayDisputeId($disputeData, $rawPayload);

        $transaction = PaymentTransaction::query()
            ->where('gateway', $gateway)
            ->where('reference', $reference)
            ->first();

        if ($transaction && $disputeKey !== null && $disputeKey !== '') {
            $existing = $transaction->disputes()->where('gateway_dispute_id', $disputeKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        if (!$transaction && $disputeKey !== null && $disputeKey !== '') {
            $existing = PaymentDispute::query()
                ->where('gateway', $gateway)
                ->where('gateway_dispute_id', $disputeKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $dispute = PaymentDispute::create([
            'payment_transaction_id' => $transaction?->id,
            'gateway'                  => $gateway,
            'kind'                     => $kind,
            'reference'                => $reference,
            'gateway_dispute_id'       => $disputeKey,
            'amount'                   => isset($disputeData['amount']) ? (float) $disputeData['amount'] : null,
            'currency'                 => isset($disputeData['currency']) ? (string) $disputeData['currency'] : null,
            'status'                   => isset($disputeData['status']) ? (string) $disputeData['status'] : null,
            'raw_payload'              => $rawPayload ?: null,
        ]);

        if ($transaction) {
            $this->handleTransactionWebhook(
                $gateway,
                $reference,
                'disputed',
                (float) ($disputeData['amount'] ?? 0),
                (string) ($disputeData['currency'] ?? config('payment-gateways.currency', 'NGN')),
                $disputeData['id'] ?? $disputeData['gateway_reference'] ?? null,
                $rawPayload
            );
        }

        return $dispute;
    }

    protected function detectGatewayDisputeId(array $disputeData, array $rawPayload = []): ?string
    {
        foreach (['id', 'dispute_id', 'gateway_dispute_id'] as $key) {
            if (!empty($disputeData[$key]) && is_scalar($disputeData[$key])) {
                return (string) $disputeData[$key];
            }
        }

        $nested = data_get($rawPayload, 'data.object.id');
        if ($nested !== null && is_scalar($nested)) {
            return (string) $nested;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Subscriptions
    // -------------------------------------------------------------------------

    /**
     * Create a new subscription record.
     */
    public function recordSubscription(string $gateway, array $data, array $response): PaymentSubscription
    {
        return PaymentSubscription::create([
            'gateway'           => $gateway,
            'plan_code'         => $data['plan_code'] ?? ($response['data']['plan_code'] ?? null),
            'plan_name'         => $data['name'] ?? null,
            'subscription_code' => $response['data']['subscription_code'] ?? ($response['data']['id'] ?? null),
            'email'             => $data['email'] ?? null,
            'amount'            => $data['amount'] ?? 0,
            'currency'          => $data['currency'] ?? config('payment-gateways.currency', 'NGN'),
            'interval'          => $data['interval'] ?? 'monthly',
            'status'            => 'pending',
            'invoice_limit'     => $data['invoice_limit'] ?? 0,
            'trial_ends_at'     => isset($data['trial_days']) ? now()->addDays($data['trial_days']) : null,
            'metadata'          => $data['metadata'] ?? null,
            'raw_response'      => $response,
        ]);
    }

    /**
     * Update subscription status from a webhook event.
     */
    public function handleSubscriptionWebhook(
        string $gateway,
        string $subscriptionCode,
        string $status,
        array $rawPayload = []
    ): ?PaymentSubscription {
        $subscription = PaymentSubscription::query()
            ->where('gateway', $gateway)
            ->where(function ($query) use ($subscriptionCode) {
                $query->where('subscription_code', $subscriptionCode)
                    ->orWhere('plan_code', $subscriptionCode);
            })
            ->first();

        if (!$subscription) {
            return null;
        }

        $updates = ['status' => $status, 'raw_response' => $rawPayload ?: $subscription->raw_response];

        if ($status === 'cancelled') {
            $updates['cancelled_at'] = now();
        }

        if ($status === 'active') {
            $updates['invoices_paid'] = $subscription->invoices_paid + 1;
        }

        $subscription->update($updates);

        return $subscription;
    }

    // -------------------------------------------------------------------------
    // Transfers
    // -------------------------------------------------------------------------

    /**
     * Create a new pending transfer record.
     */
    public function recordTransfer(string $gateway, array $data, array $response): PaymentTransfer
    {
        return PaymentTransfer::create([
            'gateway'            => $gateway,
            'reference'          => $data['reference'] ?? ($response['data']['reference'] ?? null),
            'gateway_reference'  => $response['data']['transfer_code'] ?? ($response['data']['id'] ?? null),
            'recipient_code'     => $data['recipient'] ?? ($data['recipient_code'] ?? null),
            'recipient_name'     => $data['name'] ?? null,
            'recipient_account'  => $data['account_number'] ?? ($data['phone'] ?? null),
            'bank_code'          => $data['bank_code'] ?? null,
            'bank_name'          => $data['bank_name'] ?? null,
            'amount'             => $data['amount'] ?? 0,
            'currency'           => $data['currency'] ?? config('payment-gateways.currency', 'NGN'),
            'narration'          => $data['narration'] ?? ($data['reason'] ?? null),
            'status'             => 'pending',
            'metadata'           => $data['metadata'] ?? null,
            'raw_response'       => $response,
        ]);
    }

    /**
     * Update a transfer status from a webhook event.
     */
    public function handleTransferWebhook(
        string $gateway,
        string $reference,
        string $status,
        string $gatewayReference = null,
        array $rawPayload = []
    ): ?PaymentTransfer {
        $transfer = PaymentTransfer::query()
            ->where('gateway', $gateway)
            ->where(function ($query) use ($reference) {
                $query->where('reference', $reference)
                    ->orWhere('gateway_reference', $reference);
            })
            ->first();

        if (!$transfer) {
            return null;
        }

        $updates = [
            'status'            => $status,
            'gateway_reference' => $gatewayReference ?? $transfer->gateway_reference,
            'raw_response'      => $rawPayload ?: $transfer->raw_response,
        ];

        if ($status === 'successful') {
            $updates['completed_at'] = now();
        }

        $transfer->update($updates);

        return $transfer;
    }

    // -------------------------------------------------------------------------
    // Webhook Logs
    // -------------------------------------------------------------------------

    /**
     * Persist an incoming webhook event for auditing / replay.
     */
    public function logWebhookEvent(
        string $gateway,
        WebhookEventInterface $event,
        string $status = 'received'
    ): PaymentWebhookLog {
        $data = $event->getData();

        return PaymentWebhookLog::create([
            'gateway'          => $gateway,
            'event_type'       => $event->getEventType(),
            'reference'        => $data['reference'] ?? ($data['checkout_request_id'] ?? null),
            'gateway_event_id' => $data['id'] ?? null,
            'status'           => $status,
            'payload'          => $data,
        ]);
    }

    /**
     * Log a raw payload before parsing (pre-handler).
     */
    public function logRawWebhook(
        string $gateway,
        string $eventType,
        array $payload,
        string $reference = null
    ): PaymentWebhookLog {
        return PaymentWebhookLog::create([
            'gateway'    => $gateway,
            'event_type' => $eventType,
            'reference'  => $reference,
            'status'     => 'received',
            'payload'    => $payload,
        ]);
    }
}
