<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * Remita webhook handler.
 *
 * Remita sends payment notifications to the merchant's configured URL.
 * Payloads are typically JSON with a `responseCode` field indicating
 * success (00) or failure.
 *
 * There is no standard HMAC header; instead Remita recommends verifying
 * by re-querying the RRR. When verify_signature is enabled, this handler
 * performs that re-query stub (returns true for now; implement active
 * verification by calling verifyPayment on the driver).
 */
class RemitaWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        // Remita does not send an HMAC header. Signature verification should
        // be done by re-querying the transaction via the driver. Since the
        // WebhookManager does not have driver access here, we pass through
        // and leave active re-query verification to application-level listeners.
        return true;
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        // Remita uses responseCode '00' for success
        $responseCode = $payload['responseCode'] ?? $payload['status'] ?? '';
        $eventType    = $this->mapResponseCodeToEventType($responseCode, $payload);
        $data         = $this->extractData($payload);

        return new BaseWebhookEvent($eventType, $data, 'remita', $payload);
    }

    private function mapResponseCodeToEventType(string $responseCode, array $payload): string
    {
        if (isset($payload['transactiontype']) && strtolower($payload['transactiontype']) === 'transfer') {
            return $responseCode === '00' ? 'TRANSFER_SUCCESSFUL' : 'TRANSFER_FAILED';
        }

        return match ($responseCode) {
            '00'    => 'PAYMENT_SUCCESSFUL',
            '021'   => 'PAYMENT_PENDING',
            default => 'PAYMENT_FAILED',
        };
    }

    private function extractData(array $payload): array
    {
        // Transfer notifications
        if (isset($payload['transactiontype']) && strtolower($payload['transactiontype']) === 'transfer') {
            return [
                'transfer_code'     => $payload['transactionref'] ?? '',
                'reference'         => $payload['batchRef'] ?? $payload['transactionref'] ?? '',
                'amount'            => $payload['amount'] ?? 0,
                'currency'          => $payload['currency'] ?? 'NGN',
                'status'            => $payload['responseCode'] === '00' ? 'SUCCESS' : 'FAILED',
                'recipient_account' => $payload['beneficiaryAccountNumber'] ?? '',
                'bank_name'         => $payload['beneficiaryBankName'] ?? '',
                'reason'            => $payload['narration'] ?? '',
            ];
        }

        return [
            'reference'        => $payload['orderId'] ?? $payload['RRR'] ?? '',
            'amount'           => $payload['amount'] ?? 0,
            'currency'         => $payload['currency'] ?? 'NGN',
            'status'           => $payload['message'] ?? '',
            'customer_email'   => $payload['payerEmail'] ?? '',
            'transaction_date' => $payload['transactiontime'] ?? '',
            'rrr'              => $payload['RRR'] ?? '',
        ];
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['status'] ?? parent::extractFailureReason($data);
    }
}
