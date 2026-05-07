<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * Budpay webhook handler.
 *
 * Budpay signs webhooks with HMAC-SHA512 using the secret key.
 * The signature is sent in the x-budpay-signature header (hex-encoded).
 *
 * Payload root keys:
 *   notify     — string e.g. "transaction", "payout", "virtual-account"
 *   notifyType — sub-type e.g. "successful", "failed"
 *   data       — transaction detail object
 */
class BudpayWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        $signature = $request->header('x-budpay-signature', '');
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

        $notify     = $payload['notify']     ?? '';
        $notifyType = $payload['notifyType'] ?? '';
        $data       = $payload['data']       ?? [];

        $eventType     = $notify . '.' . $notifyType;
        $processedData = $this->extractData($notify, $notifyType, $data);

        return new BaseWebhookEvent($eventType, $processedData, 'budpay', $payload);
    }

    private function extractData(string $notify, string $notifyType, array $data): array
    {
        // Virtual account funded
        if ($notify === 'virtual-account') {
            return [
                'reference'       => $data['reference'] ?? '',
                'amount'          => $data['amount'] ?? 0,
                'currency'        => $data['currency'] ?? 'NGN',
                'status'          => $data['status'] ?? '',
                'customer_email'  => $data['customer']['email'] ?? '',
                'account_number'  => $data['account_number'] ?? '',
                'bank_name'       => $data['bank_name'] ?? '',
                'transaction_date' => $data['createdAt'] ?? '',
            ];
        }

        // Payout / transfer
        if ($notify === 'payout') {
            return [
                'transfer_code'     => $data['reference'] ?? '',
                'reference'         => $data['reference'] ?? '',
                'amount'            => $data['amount'] ?? 0,
                'currency'          => $data['currency'] ?? 'NGN',
                'status'            => $data['status'] ?? '',
                'recipient_name'    => $data['account_name'] ?? '',
                'recipient_account' => $data['account_number'] ?? '',
                'bank_name'         => $data['bank_name'] ?? '',
                'reason'            => $data['narration'] ?? '',
            ];
        }

        // Default: transaction
        return [
            'reference'          => $data['reference'] ?? '',
            'amount'             => $data['amount'] ?? 0,
            'currency'           => $data['currency'] ?? 'NGN',
            'status'             => $data['status'] ?? '',
            'customer_email'     => $data['customer']['email'] ?? '',
            'transaction_date'   => $data['paidAt'] ?? $data['createdAt'] ?? '',
            'payment_method'     => $data['channel'] ?? '',
            'metadata'           => $data['metadata'] ?? [],
        ];
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['gateway_response'] ?? parent::extractFailureReason($data);
    }
}
