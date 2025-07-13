<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class PaystackWebhookHandler extends AbstractWebhookHandler
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
            'reference' => $data['reference'] ?? '',
            'amount' => isset($data['amount']) ? $data['amount'] / 100 : 0, // Convert from kobo
            'currency' => $data['currency'] ?? '',
            'status' => $data['status'] ?? '',
            'customer_email' => $data['customer']['email'] ?? '',
            'transaction_date' => $data['transaction_date'] ?? '',
            'gateway_response' => $data['gateway_response'] ?? '',
            'metadata' => $data['metadata'] ?? [],
        ];

        return new BaseWebhookEvent(
            $eventType,
            $processedData,
            'paystack',
            $payload
        );
    }

    protected function getSignatureFromRequest(Request $request): string
    {
        return $request->header('x-paystack-signature', '');
    }

    protected function calculateExpectedSignature(string $payload): string
    {
        $secret = $this->config['webhook_secret'] ?? '';
        return hash_hmac('sha512', $payload, $secret);
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['gateway_response'] ?? parent::extractFailureReason($data);
    }
}
