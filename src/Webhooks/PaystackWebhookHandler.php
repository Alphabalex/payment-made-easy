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
        $data      = $payload['data'] ?? [];

        $processedData = $this->extractData($eventType, $data);

        return new BaseWebhookEvent($eventType, $processedData, 'paystack', $payload);
    }

    private function extractData(string $eventType, array $data): array
    {
        // Subscription events
        if (str_starts_with($eventType, 'subscription.') || str_starts_with($eventType, 'invoice.')) {
            return [
                'subscription_code' => $data['subscription_code'] ?? $data['id'] ?? '',
                'plan_code'         => $data['plan']['plan_code'] ?? '',
                'customer_email'    => $data['customer']['email'] ?? '',
                'status'            => $data['status'] ?? '',
                'amount'            => isset($data['amount']) ? $data['amount'] / 100 : 0,
                'currency'          => $data['plan']['currency'] ?? '',
                'next_payment_date' => $data['next_payment_date'] ?? '',
            ];
        }

        // Transfer events
        if (str_starts_with($eventType, 'transfer.')) {
            return [
                'transfer_code'    => $data['transfer_code'] ?? '',
                'reference'        => $data['reference'] ?? '',
                'amount'           => isset($data['amount']) ? $data['amount'] / 100 : 0,
                'currency'         => $data['currency'] ?? '',
                'status'           => $data['status'] ?? '',
                'recipient_name'   => $data['recipient']['details']['account_name'] ?? '',
                'recipient_account' => $data['recipient']['details']['account_number'] ?? '',
                'bank_name'        => $data['recipient']['details']['bank_name'] ?? '',
                'reason'           => $data['reason'] ?? '',
            ];
        }

        // Dispute events
        if (str_starts_with($eventType, 'dispute.')) {
            return [
                'reference'    => $data['transaction']['reference'] ?? '',
                'amount'       => isset($data['amount']) ? $data['amount'] / 100 : 0,
                'currency'     => $data['currency'] ?? '',
                'status'       => $data['status'] ?? '',
                'category'     => $data['category'] ?? '',
                'customer_email' => $data['transaction']['customer']['email'] ?? '',
            ];
        }

        // Default: payment / refund events
        return [
            'reference'        => $data['reference'] ?? '',
            'amount'           => isset($data['amount']) ? $data['amount'] / 100 : 0,
            'currency'         => $data['currency'] ?? '',
            'status'           => $data['status'] ?? '',
            'customer_email'   => $data['customer']['email'] ?? '',
            'transaction_date' => $data['transaction_date'] ?? '',
            'gateway_response' => $data['gateway_response'] ?? '',
            'metadata'         => $data['metadata'] ?? [],
        ];
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
