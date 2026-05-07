<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class SeerbitWebhookHandler extends AbstractWebhookHandler
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

        $eventType = $payload['eventType'] ?? '';
        $data = $payload['data'] ?? [];

        // Extract relevant payment information
        $processedData = [
            'reference' => $data['paymentReference'] ?? '',
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? '',
            'status' => $data['transactionStatus'] ?? '',
            'gateway_ref' => $data['gatewayref'] ?? '',
            'customer_email' => $data['email'] ?? '',
            'transaction_date' => $data['transactionProcessedTime'] ?? '',
            'gateway_response' => $data['gatewayMessage'] ?? '',
            'payment_method' => $data['paymentMethod'] ?? '',
            'channel_type' => $data['channelType'] ?? '',
            'country' => $data['country'] ?? '',
            'fee' => $data['fee'] ?? 0,
            'metadata' => $data['customization'] ?? [],
        ];

        // Handle refund events
        if (strpos($eventType, 'REFUND') !== false) {
            $processedData = array_merge($processedData, [
                'refund_reference' => $data['refundReference'] ?? '',
                'refund_amount' => $data['refundAmount'] ?? 0,
                'refund_type' => $data['refundType'] ?? '',
                'refund_reason' => $data['refundReason'] ?? '',
                'refund_status' => $data['refundStatus'] ?? '',
            ]);
        }

        return new BaseWebhookEvent(
            $eventType,
            $processedData,
            'seerbit',
            $payload
        );
    }

    protected function getSignatureFromRequest(Request $request): string
    {
        return $request->header('x-seerbit-signature', '');
    }

    protected function calculateExpectedSignature(string $payload): string
    {
        $secret = $this->config['webhook_secret'] ?? '';
        return hash_hmac('sha256', $payload, $secret);
    }

    protected function extractFailureReason(array $data): string
    {
        return $data['gateway_response'] ?? $data['gatewayMessage'] ?? parent::extractFailureReason($data);
    }
}
