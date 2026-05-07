<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * MTN MoMo webhook handler.
 *
 * MTN MoMo delivers asynchronous callbacks to the callbackUrl provided when
 * initiating a RequestToPay or Transfer. No HMAC signature header is sent;
 * security relies on HTTPS + callback URL secrecy.
 *
 * Collection callback payload (RequestToPay result):
 *   { financialTransactionId, externalId, amount, currency, payer, status, reason }
 *
 * Disbursement callback payload (Transfer result):
 *   { financialTransactionId, externalId, amount, currency, payee, status, reason }
 *
 * status values: SUCCESSFUL | FAILED | PENDING
 */
class MTNMoMoWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        // MTN MoMo does not send an HMAC signature.
        // Secure your callback URLs via HTTPS and URL obfuscation.
        return true;
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        [$eventType, $data] = $this->classifyPayload($payload);

        $processedData = $this->extractData($eventType, $payload);

        return new BaseWebhookEvent($eventType, $processedData, 'mtnmomo', $payload);
    }

    private function classifyPayload(array $payload): array
    {
        $status = strtoupper($payload['status'] ?? 'FAILED');

        // Determine direction: collection has 'payer', disbursement has 'payee'
        if (isset($payload['payee'])) {
            $eventType = $status === 'SUCCESSFUL' ? 'TRANSFER_SUCCESSFUL' : 'TRANSFER_FAILED';
        } else {
            $eventType = $status === 'SUCCESSFUL' ? 'PAYMENT_SUCCESSFUL'
                : ($status === 'PENDING' ? 'PAYMENT_PENDING' : 'PAYMENT_FAILED');
        }

        return [$eventType, $payload];
    }

    private function extractData(string $eventType, array $payload): array
    {
        if (str_starts_with($eventType, 'TRANSFER_')) {
            return [
                'reference'      => $payload['externalId'] ?? '',
                'transaction_id' => $payload['financialTransactionId'] ?? '',
                'amount'         => $payload['amount'] ?? null,
                'currency'       => $payload['currency'] ?? 'KES',
                'recipient'      => $payload['payee']['partyId'] ?? '',
                'status'         => $payload['status'] ?? '',
                'reason'         => $payload['reason']['message'] ?? $payload['reason'] ?? '',
            ];
        }

        return [
            'reference'      => $payload['externalId'] ?? '',
            'transaction_id' => $payload['financialTransactionId'] ?? '',
            'amount'         => $payload['amount'] ?? null,
            'currency'       => $payload['currency'] ?? 'KES',
            'phone'          => $payload['payer']['partyId'] ?? '',
            'status'         => $payload['status'] ?? '',
            'reason'         => $payload['reason']['message'] ?? $payload['reason'] ?? '',
        ];
    }
}
