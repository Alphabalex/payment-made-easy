<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * M-Pesa webhook handler.
 *
 * Safaricom delivers two callback types to your registered URLs:
 *
 * 1. STK Push result  — POST to CallBackURL
 *    Root: { Body: { stkCallback: { ResultCode, CheckoutRequestID, CallbackMetadata } } }
 *
 * 2. B2C result       — POST to ResultURL
 *    Root: { Result: { ResultCode, TransactionID, ResultParameters } }
 *
 * Safaricom does not send an HMAC signature header; security relies on IP
 * allowlisting + TLS. verifySignature() always returns true.
 */
class MPesaWebhookHandler extends AbstractWebhookHandler
{
    protected function requiresConfiguredSigningSecret(): bool
    {
        return false;
    }

    public function verifySignature(Request $request): bool
    {
        // Safaricom does not provide an HMAC signature.
        // Secure your endpoint via IP allowlisting (Safaricom IP ranges) + HTTPS.
        return true;
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        [$eventType, $data] = $this->normalisePayload($payload);

        $processedData = $this->extractData($eventType, $data);

        return new BaseWebhookEvent($eventType, $processedData, 'mpesa', $payload);
    }

    /** Distinguish STK Push vs B2C and extract the core data array. */
    private function normalisePayload(array $payload): array
    {
        // STK Push callback
        if (isset($payload['Body']['stkCallback'])) {
            $stk        = $payload['Body']['stkCallback'];
            $resultCode = (int) ($stk['ResultCode'] ?? 1);
            $eventType  = $resultCode === 0 ? 'STK_PUSH_SUCCESS' : 'STK_PUSH_FAILED';
            return [$eventType, $stk];
        }

        // B2C / B2B result
        if (isset($payload['Result'])) {
            $result     = $payload['Result'];
            $resultCode = (int) ($result['ResultCode'] ?? 1);
            $cmdId      = $result['ResultType'] ?? '';
            $eventType  = $resultCode === 0 ? 'B2C_SUCCESS' : 'B2C_FAILED';
            return [$eventType, $result];
        }

        // C2B confirmation / validation
        if (isset($payload['TransactionType'])) {
            return ['C2B_PAYMENT', $payload];
        }

        return ['UNKNOWN', $payload];
    }

    private function extractData(string $eventType, array $data): array
    {
        if (str_starts_with($eventType, 'STK_PUSH')) {
            $meta  = $this->parseCallbackMetadata($data['CallbackMetadata']['Item'] ?? []);
            $success = $eventType === 'STK_PUSH_SUCCESS';
            return [
                'reference'      => $data['CheckoutRequestID'] ?? '',
                'transaction_id' => $meta['MpesaReceiptNumber'] ?? '',
                'amount'         => $meta['Amount'] ?? null,
                'currency'       => 'KES',
                'phone'          => $meta['PhoneNumber'] ?? '',
                'status'         => $success ? 'success' : 'failed',
                'result_code'    => $data['ResultCode'] ?? '',
                'result_desc'    => $data['ResultDesc'] ?? '',
            ];
        }

        if (str_starts_with($eventType, 'B2C')) {
            $params  = $this->parseResultParameters($data['ResultParameters']['ResultParameter'] ?? []);
            $success = $eventType === 'B2C_SUCCESS';
            return [
                'reference'      => $data['OriginatorConversationID'] ?? '',
                'transaction_id' => $data['TransactionID'] ?? $params['TransactionReceipt'] ?? '',
                'amount'         => $params['TransactionAmount'] ?? null,
                'currency'       => 'KES',
                'recipient'      => $params['ReceiverPartyPublicName'] ?? '',
                'status'         => $success ? 'success' : 'failed',
                'result_code'    => $data['ResultCode'] ?? '',
                'result_desc'    => $data['ResultDesc'] ?? '',
            ];
        }

        // C2B
        return [
            'reference'      => $data['BillRefNumber'] ?? '',
            'transaction_id' => $data['TransID'] ?? '',
            'amount'         => $data['TransAmount'] ?? null,
            'currency'       => 'KES',
            'phone'          => $data['MSISDN'] ?? '',
            'status'         => 'success',
        ];
    }

    private function parseCallbackMetadata(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (isset($item['Name'])) {
                $result[$item['Name']] = $item['Value'] ?? null;
            }
        }
        return $result;
    }

    private function parseResultParameters(array $params): array
    {
        $result = [];
        foreach ($params as $param) {
            if (isset($param['Key'])) {
                $result[$param['Key']] = $param['Value'] ?? null;
            }
        }
        return $result;
    }

    protected function getSignatureFromRequest(\Illuminate\Http\Request $request): string
    {
        return '';
    }

    protected function calculateExpectedSignature(string $payload): string
    {
        return '';
    }
}
