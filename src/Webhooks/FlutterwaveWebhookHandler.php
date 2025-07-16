<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class FlutterwaveWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        $signature = $this->getSignatureFromRequest($request);
        $payload = $request->getContent();
        $expectedSignature = $this->calculateExpectedSignature($payload);

        return hash_equals($expectedSignature, $signature);
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookException('Invalid JSON payload');
        }

        $eventType = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        // Extract relevant payment information
        $processedData = [
            'reference' => $data['tx_ref'] ?? $data['flw_ref'] ?? '',
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? '',
            'status' => $data['status'] ?? '',
            'customer_email' => $data['customer']['email'] ?? '',
            'transaction_date' => $data['created_at'] ?? '',
            'processor_response' => $data['processor_response'] ?? '',
            'metadata' => $data['meta'] ?? [],
        ];

        return new BaseWebhookEvent(
            $eventType,
            $processedData,
            'flutterwave',
            $payload
        );
    }

    protected function getSignatureFromRequest(Request $request): string
    {
        return $request->header('verif-hash', '');
    }

    protected function calculateExpectedSignature(string $payload): string
    {
        $secret = $this->config['webhook_secret'] ?? '';
        return $secret; // Flutterwave sends the secret directly
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['processor_response'] ?? parent::extractFailureReason($data);
    }
}
