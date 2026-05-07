<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * PayPal webhook handler.
 *
 * PayPal signs webhooks using RSA-SHA256 with a PayPal-issued certificate.
 * Full cryptographic verification requires fetching the cert from PAYPAL-CERT-URL
 * and verifying the PAYPAL-TRANSMISSION-SIG header.
 *
 * This handler verifies that all required PayPal signature headers are present.
 * For strict production use, integrate with POST /v1/notifications/verify-webhook-signature.
 *
 * Key payload fields:
 *   event_type  — e.g. "PAYMENT.CAPTURE.COMPLETED", "BILLING.SUBSCRIPTION.ACTIVATED"
 *   resource    — the event-specific object
 */
class PayPalWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        // PayPal uses RSA-SHA256 cert-based signing, not simple HMAC.
        // We confirm required headers are present as a first-pass guard.
        $required = ['PAYPAL-TRANSMISSION-ID', 'PAYPAL-TRANSMISSION-TIME', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-SIG'];
        foreach ($required as $header) {
            if (!$request->hasHeader($header)) {
                return false;
            }
        }

        return true;
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        $eventType = $payload['event_type'] ?? '';
        $resource  = $payload['resource'] ?? [];

        $processedData = $this->extractData($eventType, $resource);

        return new BaseWebhookEvent($eventType, $processedData, 'paypal', $payload);
    }

    private function extractData(string $eventType, array $resource): array
    {
        // Billing subscriptions
        if (str_starts_with($eventType, 'BILLING.SUBSCRIPTION.')) {
            return [
                'subscription_code' => $resource['id'] ?? '',
                'plan_code'         => $resource['plan_id'] ?? '',
                'email'             => $resource['subscriber']['email_address'] ?? '',
                'status'            => $resource['status'] ?? '',
                'reference'         => $resource['id'] ?? '',
                'amount'            => $resource['billing_info']['last_payment']['amount']['value'] ?? null,
                'currency'          => $resource['billing_info']['last_payment']['amount']['currency_code'] ?? null,
                'next_billing_date' => $resource['billing_info']['next_billing_time'] ?? null,
            ];
        }

        // Payouts
        if (str_starts_with($eventType, 'PAYMENT.PAYOUTS-ITEM.')) {
            return [
                'reference'      => $resource['payout_item']['sender_item_id'] ?? $resource['payout_item_id'] ?? '',
                'amount'         => $resource['payout_item']['amount']['value'] ?? null,
                'currency'       => $resource['payout_item']['amount']['currency'] ?? null,
                'recipient'      => $resource['payout_item']['receiver'] ?? '',
                'status'         => $resource['transaction_status'] ?? '',
                'transaction_id' => $resource['transaction_id'] ?? '',
            ];
        }

        // Disputes / chargebacks
        if (str_starts_with($eventType, 'CUSTOMER.DISPUTE.') || str_starts_with($eventType, 'RISK.DISPUTE.')) {
            return [
                'reference'  => $resource['disputed_transactions'][0]['seller_transaction_id'] ?? $resource['dispute_id'] ?? '',
                'dispute_id' => $resource['dispute_id'] ?? '',
                'reason'     => $resource['reason'] ?? '',
                'amount'     => $resource['dispute_amount']['value'] ?? null,
                'currency'   => $resource['dispute_amount']['currency_code'] ?? null,
                'status'     => $resource['status'] ?? '',
            ];
        }

        // Default: PAYMENT.CAPTURE.* / CHECKOUT.ORDER.*
        $purchaseUnit = $resource['purchase_units'][0] ?? $resource;
        $capture      = $purchaseUnit['payments']['captures'][0] ?? [];

        return [
            'reference'      => $purchaseUnit['reference_id'] ?? $resource['id'] ?? '',
            'transaction_id' => $capture['id'] ?? $resource['id'] ?? '',
            'amount'         => $capture['amount']['value'] ?? $purchaseUnit['amount']['value'] ?? $resource['amount']['value'] ?? null,
            'currency'       => $capture['amount']['currency_code'] ?? $purchaseUnit['amount']['currency_code'] ?? null,
            'status'         => $resource['status'] ?? '',
            'email'          => $resource['payer']['email_address'] ?? '',
            'payer_id'       => $resource['payer']['payer_id'] ?? '',
        ];
    }
}
