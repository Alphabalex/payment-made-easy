<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * Razorpay webhook handler.
 *
 * Razorpay signs webhooks with HMAC-SHA256 using the webhook secret
 * configured in the Razorpay dashboard.
 * Signature is in the X-Razorpay-Signature header.
 *
 * Key event types:
 *   payment.captured        — one-time payment succeeded
 *   payment.failed          — payment failed
 *   order.paid              — order fully paid
 *   refund.created          — refund initiated
 *   subscription.activated  — subscription live
 *   subscription.charged    — subscription renewal payment
 *   subscription.cancelled  — subscription cancelled
 *   subscription.paused     — subscription paused
 *   subscription.resumed    — subscription resumed
 *   payout.processed        — disbursement succeeded
 *   payout.failed           — disbursement failed
 *   payment_link.paid       — payment link paid
 *   dispute.created         — chargeback dispute opened
 */
class RazorpayWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        $signature = $request->header('X-Razorpay-Signature', '');
        $payload   = $request->getContent();
        $secret    = $this->config['webhook_secret'] ?? '';

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        $eventType = $payload['event'] ?? '';
        $entity    = $payload['payload'] ?? [];

        $processedData = $this->extractData($eventType, $entity);

        return new BaseWebhookEvent($eventType, $processedData, 'razorpay', $payload);
    }

    private function extractData(string $eventType, array $entity): array
    {
        // Subscriptions
        if (str_starts_with($eventType, 'subscription.')) {
            $sub = $entity['subscription']['entity'] ?? [];
            $pmt = $entity['payment']['entity'] ?? [];
            return [
                'subscription_code' => $sub['id'] ?? '',
                'plan_code'         => $sub['plan_id'] ?? '',
                'email'             => $sub['email_notify'] ?? '',
                'status'            => $sub['status'] ?? '',
                'reference'         => $sub['id'] ?? '',
                'amount'            => isset($pmt['amount']) ? $pmt['amount'] / 100 : null,
                'currency'          => $pmt['currency'] ?? null,
                'next_charge_at'    => $sub['charge_at'] ?? null,
            ];
        }

        // Payouts / disbursements
        if (str_starts_with($eventType, 'payout.')) {
            $payout = $entity['payout']['entity'] ?? [];
            return [
                'reference'      => $payout['reference_id'] ?? $payout['id'] ?? '',
                'transaction_id' => $payout['id'] ?? '',
                'amount'         => isset($payout['amount']) ? $payout['amount'] / 100 : null,
                'currency'       => $payout['currency'] ?? null,
                'status'         => $payout['status'] ?? '',
                'mode'           => $payout['mode'] ?? '',
                'utr'            => $payout['utr'] ?? '',
            ];
        }

        // Refunds
        if (str_starts_with($eventType, 'refund.')) {
            $refund = $entity['refund']['entity'] ?? [];
            return [
                'reference'      => $refund['payment_id'] ?? '',
                'refund_id'      => $refund['id'] ?? '',
                'amount'         => isset($refund['amount']) ? $refund['amount'] / 100 : null,
                'currency'       => $refund['currency'] ?? null,
                'status'         => $refund['status'] ?? '',
            ];
        }

        // Disputes
        if (str_starts_with($eventType, 'dispute.')) {
            $dispute = $entity['dispute']['entity'] ?? [];
            return [
                'reference'  => $dispute['payment_id'] ?? '',
                'dispute_id' => $dispute['id'] ?? '',
                'amount'     => isset($dispute['amount']) ? $dispute['amount'] / 100 : null,
                'currency'   => $dispute['currency'] ?? null,
                'reason'     => $dispute['reason_description'] ?? '',
                'status'     => $dispute['status'] ?? '',
            ];
        }

        // Payment links
        if (str_starts_with($eventType, 'payment_link.')) {
            $pl  = $entity['payment_link']['entity'] ?? [];
            $pmt = $entity['payment']['entity'] ?? [];
            return [
                'reference'      => $pl['reference_id'] ?? $pl['id'] ?? '',
                'transaction_id' => $pmt['id'] ?? '',
                'amount'         => isset($pmt['amount']) ? $pmt['amount'] / 100 : null,
                'currency'       => $pmt['currency'] ?? null,
                'email'          => $pmt['email'] ?? '',
                'status'         => $pl['status'] ?? '',
            ];
        }

        // Default: payment.captured / payment.failed / order.paid
        $payment = $entity['payment']['entity'] ?? $entity['order']['entity'] ?? [];
        return [
            'reference'      => $payment['order_id'] ?? $payment['receipt'] ?? $payment['id'] ?? '',
            'transaction_id' => $payment['id'] ?? '',
            'amount'         => isset($payment['amount']) ? $payment['amount'] / 100 : null,
            'currency'       => $payment['currency'] ?? null,
            'email'          => $payment['email'] ?? '',
            'phone'          => $payment['contact'] ?? '',
            'status'         => $payment['status'] ?? '',
            'method'         => $payment['method'] ?? '',
            'error_reason'   => $payment['error_description'] ?? null,
        ];
    }
}
