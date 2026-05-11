<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

/**
 * Interswitch webhook handler.
 *
 * Interswitch IPG sends payment notifications as POST requests.
 * The payload is signed with HMAC-SHA512 using the client secret.
 * The signature is sent in the x-interswitch-signature header (hex-encoded).
 *
 * Payload root keys (IPG v3 notification):
 *   transactionReference — merchant-side reference
 *   responseCode         — '00' = success
 *   responseDescription  — human-readable result
 *   amount               — in kobo
 *   purchaseReference    — Interswitch-side reference
 */
class InterswitchWebhookHandler extends AbstractWebhookHandler
{
    protected function configuredSigningSecret(): ?string
    {
        $s = trim((string) ($this->config['client_secret'] ?? $this->config['webhook_secret'] ?? ''));

        return $s !== '' ? $s : null;
    }

    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        $signature = $request->header('x-interswitch-signature', '');
        $payload   = $request->getContent();
        $secret    = $this->config['client_secret'] ?? $this->config['webhook_secret'] ?? '';

        $expected = hash_hmac('sha512', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        $responseCode = $payload['responseCode'] ?? '';
        $eventType    = $this->mapResponseCode($responseCode);
        $data         = $this->extractData($payload);

        return new BaseWebhookEvent($eventType, $data, 'interswitch', $payload);
    }

    private function mapResponseCode(string $code): string
    {
        return match ($code) {
            '00'    => 'PAYMENT_SUCCESSFUL',
            'T0'    => 'PAYMENT_PENDING',
            default => 'PAYMENT_FAILED',
        };
    }

    private function extractData(array $payload): array
    {
        return [
            'reference'        => $payload['transactionReference'] ?? '',
            'amount'           => isset($payload['amount']) ? $payload['amount'] / 100 : 0,
            'currency'         => $payload['currency'] ?? 'NGN',
            'status'           => $payload['responseDescription'] ?? '',
            'customer_email'   => $payload['customerEmail'] ?? '',
            'gateway_ref'      => $payload['purchaseReference'] ?? '',
            'transaction_date' => $payload['transactionDate'] ?? '',
            'payment_method'   => $payload['paymentChannel'] ?? '',
        ];
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['status'] ?? parent::extractFailureReason($data);
    }

    protected function getSignatureFromRequest(\Illuminate\Http\Request $request): string
    {
        return $request->header('x-interswitch-signature', '');
    }

    protected function calculateExpectedSignature(string $payload): string
    {
        $secret = $this->config['client_secret'] ?? $this->config['webhook_secret'] ?? '';
        return hash_hmac('sha512', $payload, $secret);
    }
}
