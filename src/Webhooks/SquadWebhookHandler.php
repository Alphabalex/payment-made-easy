<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * Squad webhook handler.
 *
 * Squad signs webhooks with HMAC-SHA512 using the secret key.
 * Signature is sent in the x-squad-signature header (hex-encoded).
 *
 * Payload root keys:
 *   Event     — string e.g. "charge_successful", "transfer_complete"
 *   Body      — object with the transaction detail
 */
class SquadWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        $signature = $request->header('x-squad-signature', '');
        $payload   = $request->getContent();
        $secret    = $this->config['webhook_secret'] ?? '';

        $expected = hash_hmac('sha512', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        $eventType = $payload['Event'] ?? '';
        $data      = $payload['Body'] ?? [];

        $processedData = $this->extractData($eventType, $data);

        return new BaseWebhookEvent($eventType, $processedData, 'squad', $payload);
    }

    private function extractData(string $eventType, array $data): array
    {
        // Virtual account funded
        if ($eventType === 'virtual_account_payment') {
            return [
                'reference'       => $data['transaction_ref'] ?? '',
                'amount'          => isset($data['amount']) ? $data['amount'] / 100 : 0,
                'currency'        => $data['currency'] ?? 'NGN',
                'status'          => $data['status'] ?? '',
                'customer_email'  => $data['email'] ?? '',
                'account_number'  => $data['virtual_account_number'] ?? '',
                'transaction_date' => $data['createdAt'] ?? '',
            ];
        }

        // Transfer / payout events
        if (str_starts_with($eventType, 'transfer_')) {
            return [
                'transfer_code'     => $data['transaction_reference'] ?? '',
                'reference'         => $data['transaction_ref'] ?? $data['reference'] ?? '',
                'amount'            => isset($data['amount']) ? $data['amount'] / 100 : 0,
                'currency'          => $data['currency_id'] ?? 'NGN',
                'status'            => $data['status'] ?? '',
                'recipient_name'    => $data['account_name'] ?? '',
                'recipient_account' => $data['account_number'] ?? '',
                'bank_name'         => $data['bank_name'] ?? '',
                'reason'            => $data['remark'] ?? '',
            ];
        }

        // Default: charge events
        return [
            'reference'          => $data['transaction_ref'] ?? '',
            'amount'             => isset($data['transaction_amount']) ? $data['transaction_amount'] / 100 : 0,
            'currency'           => $data['currency'] ?? 'NGN',
            'status'             => $data['transaction_status'] ?? '',
            'customer_email'     => $data['email'] ?? '',
            'transaction_date'   => $data['createdAt'] ?? '',
            'payment_method'     => $data['transaction_type'] ?? '',
            'metadata'           => $data['meta_data'] ?? [],
        ];
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['gateway_response'] ?? parent::extractFailureReason($data);
    }
}
