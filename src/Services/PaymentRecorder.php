<?php

namespace NexusPay\PaymentMadeEasy\Services;

use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Models\PaymentSubscription;
use NexusPay\PaymentMadeEasy\Models\PaymentTransaction;
use NexusPay\PaymentMadeEasy\Models\PaymentTransfer;
use NexusPay\PaymentMadeEasy\Models\PaymentWebhookLog;

/**
 * PaymentRecorder
 *
 * Opt-in service that persists payment activity to the database.
 * Enable by adding `'record' => true` in payment-gateways.php under `recording`.
 *
 * Usage (direct):
 *   app(PaymentRecorder::class)->recordTransaction('paystack', $initData, $gatewayResponse);
 *
 * Or via events: listen to PaymentSuccessful / TransferSuccessful etc. and call the recorder.
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
        $subscription = PaymentSubscription::where('subscription_code', $subscriptionCode)
            ->orWhere('plan_code', $subscriptionCode)
            ->where('gateway', $gateway)
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
        $transfer = PaymentTransfer::where('reference', $reference)
            ->orWhere('gateway_reference', $reference)
            ->where('gateway', $gateway)
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
