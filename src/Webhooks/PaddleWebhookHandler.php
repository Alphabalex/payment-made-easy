<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * Paddle webhook handler.
 *
 * Paddle Billing signs webhook notifications with HMAC-SHA256.
 * The signature is in the Paddle-Signature header in the format:
 *   ts=<timestamp>;h1=<hmac_hex>
 *
 * Verification: HMAC-SHA256(ts + ":" + raw_body, webhook_secret_key)
 *
 * Key event types:
 *   transaction.completed   — one-time payment succeeded
 *   transaction.payment_failed — payment failed
 *   subscription.activated  — new subscription active
 *   subscription.canceled   — subscription cancelled
 *   subscription.past_due   — renewal payment failed
 *   subscription.updated    — subscription changed
 *   subscription.paused     — subscription paused
 *   subscription.resumed    — subscription resumed
 *   adjustment.created      — refund/credit issued
 *   dispute.created         — chargeback raised
 */
class PaddleWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        $header = $request->header('Paddle-Signature', '');
        if (empty($header)) {
            return false;
        }

        // Parse ts=...;h1=...
        $parts = [];
        foreach (explode(';', $header) as $part) {
            [$key, $value] = explode('=', $part, 2) + ['', ''];
            $parts[$key] = $value;
        }

        $ts        = $parts['ts'] ?? '';
        $signature = $parts['h1'] ?? '';
        $secret    = $this->config['webhook_secret'] ?? '';
        $payload   = $request->getContent();

        $expected = hash_hmac('sha256', $ts . ':' . $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        $eventType = $payload['event_type'] ?? '';
        $data      = $payload['data'] ?? [];

        $processedData = $this->extractData($eventType, $data);

        return new BaseWebhookEvent($eventType, $processedData, 'paddle', $payload);
    }

    private function extractData(string $eventType, array $data): array
    {
        // Subscriptions
        if (str_starts_with($eventType, 'subscription.')) {
            $items  = $data['items'][0] ?? [];
            $price  = $items['price'] ?? [];
            return [
                'subscription_code' => $data['id'] ?? '',
                'plan_code'         => $price['id'] ?? '',
                'email'             => $data['customer']['email'] ?? '',
                'customer_id'       => $data['customer_id'] ?? '',
                'status'            => $data['status'] ?? '',
                'reference'         => $data['id'] ?? '',
                'amount'            => isset($price['unit_price']['amount']) ? (int) $price['unit_price']['amount'] / 100 : null,
                'currency'          => $price['unit_price']['currency_code'] ?? null,
                'next_billing_at'   => $data['next_billed_at'] ?? null,
            ];
        }

        // Refunds / adjustments
        if (str_starts_with($eventType, 'adjustment.')) {
            return [
                'reference'      => $data['transaction_id'] ?? '',
                'adjustment_id'  => $data['id'] ?? '',
                'amount'         => isset($data['totals']['total']) ? (int) $data['totals']['total'] / 100 : null,
                'currency'       => $data['currency_code'] ?? null,
                'status'         => $data['status'] ?? '',
                'reason'         => $data['reason'] ?? '',
            ];
        }

        // Disputes
        if (str_starts_with($eventType, 'dispute.')) {
            return [
                'reference'  => $data['transaction_id'] ?? $data['id'] ?? '',
                'dispute_id' => $data['id'] ?? '',
                'amount'     => isset($data['totals']['total']) ? (int) $data['totals']['total'] / 100 : null,
                'currency'   => $data['currency_code'] ?? null,
                'status'     => $data['status'] ?? '',
                'reason'     => $data['reason'] ?? '',
            ];
        }

        // Default: transaction.completed / transaction.payment_failed
        $items   = $data['items'][0] ?? [];
        $details = $data['details'] ?? [];
        return [
            'reference'      => $data['custom_data']['reference'] ?? $data['id'] ?? '',
            'transaction_id' => $data['id'] ?? '',
            'amount'         => isset($details['totals']['grand_total']) ? (int) $details['totals']['grand_total'] / 100 : null,
            'currency'       => $data['currency_code'] ?? null,
            'email'          => $data['customer']['email'] ?? '',
            'customer_id'    => $data['customer_id'] ?? '',
            'status'         => $data['status'] ?? '',
        ];
    }
}
