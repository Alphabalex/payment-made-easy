<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * Monnify webhook handler.
 *
 * Monnify sends webhook notifications signed with HMAC-SHA512 using the
 * merchant's secret key. The signature is base64-encoded and sent in the
 * Monnify-Signature header.
 *
 * Payload root keys:
 *   eventType   — string e.g. "SUCCESSFUL_TRANSACTION", "FAILED_TRANSACTION",
 *                 "RESERVED_ACCOUNT_FUNDED", "DISBURSEMENT_COMPLETED", etc.
 *   eventData   — object with the transaction/event detail
 */
class MonnifyWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        $signature = $request->header('monnify-signature', '');
        $payload   = $request->getContent();
        $secret    = $this->config['webhook_secret'] ?? $this->config['secret_key'] ?? '';

        $expected = base64_encode(hash_hmac('sha512', $payload, $secret, true));

        return hash_equals($expected, $signature);
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        $eventType = $payload['eventType'] ?? '';
        $data      = $payload['eventData'] ?? [];

        $processedData = $this->extractData($eventType, $data);

        return new BaseWebhookEvent($eventType, $processedData, 'monnify', $payload);
    }

    private function extractData(string $eventType, array $data): array
    {
        // Reserved-account funded (virtual account credit)
        if ($eventType === 'RESERVED_ACCOUNT_FUNDED') {
            return [
                'reference'          => $data['paymentReference'] ?? '',
                'amount'             => $data['amountPaid'] ?? 0,
                'currency'           => $data['currency'] ?? 'NGN',
                'status'             => 'PAID',
                'customer_email'     => $data['customer']['email'] ?? '',
                'transaction_date'   => $data['paidOn'] ?? '',
                'account_number'     => $data['destinationAccountInformation']['accountNumber'] ?? '',
                'bank_name'          => $data['destinationAccountInformation']['bankName'] ?? '',
                'metadata'           => $data['metaData'] ?? [],
            ];
        }

        // Disbursement / transfer events
        if (str_starts_with($eventType, 'DISBURSEMENT_')) {
            return [
                'transfer_code'     => $data['transactionReference'] ?? '',
                'reference'         => $data['reference'] ?? '',
                'amount'            => $data['amount'] ?? 0,
                'currency'          => $data['currency'] ?? 'NGN',
                'status'            => $data['status'] ?? '',
                'recipient_name'    => $data['destinationAccountName'] ?? '',
                'recipient_account' => $data['destinationAccountNumber'] ?? '',
                'bank_name'         => $data['destinationBankName'] ?? '',
                'reason'            => $data['narration'] ?? '',
            ];
        }

        // Default: payment/transaction events
        return [
            'reference'          => $data['paymentReference'] ?? '',
            'amount'             => $data['amountPaid'] ?? $data['amount'] ?? 0,
            'currency'           => $data['currency'] ?? 'NGN',
            'status'             => $data['paymentStatus'] ?? '',
            'customer_email'     => $data['customer']['email'] ?? '',
            'transaction_date'   => $data['paidOn'] ?? '',
            'payment_method'     => $data['paymentMethod'] ?? '',
            'metadata'           => $data['metaData'] ?? [],
        ];
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['message'] ?? parent::extractFailureReason($data);
    }

    protected function getSignatureFromRequest(Request $request): string
    {
        return $request->header('monnify-signature', '');
    }

    protected function calculateExpectedSignature(string $payload): string
    {
        $secret = $this->config['webhook_secret'] ?? $this->config['secret_key'] ?? '';
        return base64_encode(hash_hmac('sha512', $payload, $secret, true));
    }
}
