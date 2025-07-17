<?php

namespace NexusPay\PaymentMadeEasy\Webhooks;

use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use NexusPay\PaymentMadeEasy\Contracts\WebhookEventInterface;
use NexusPay\PaymentMadeEasy\Events\BaseWebhookEvent;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class StripeWebhookHandler extends AbstractWebhookHandler
{
    public function verifySignature(Request $request): bool
    {
        if (!config('payment-gateways.webhooks.verify_signature', true)) {
            return true;
        }

        try {
            $signature = $this->getSignatureFromRequest($request);
            $payload = $request->getContent();
            $secret = $this->config['webhook_secret'] ?? '';

            Webhook::constructEvent($payload, $signature, $secret);
            return true;
        } catch (SignatureVerificationException $e) {
            return false;
        }
    }

    public function parsePayload(Request $request): WebhookEventInterface
    {
        try {
            $signature = $this->getSignatureFromRequest($request);
            $payload = $request->getContent();
            $secret = $this->config['webhook_secret'] ?? '';

            $event = Webhook::constructEvent($payload, $signature, $secret);
            $eventData = $event->data->object;

            // Extract relevant payment information based on event type
            $processedData = $this->processStripeEventData($event->type, $eventData);

            return new BaseWebhookEvent(
                $event->type,
                $processedData,
                'stripe',
                json_decode($payload, true)
            );
        } catch (\Exception $e) {
            throw new WebhookException('Failed to parse Stripe webhook: ' . $e->getMessage());
        }
    }

    protected function processStripeEventData(string $eventType, $eventData): array
    {
        $baseData = [
            'id' => $eventData->id ?? '',
            'object' => $eventData->object ?? '',
            'created' => isset($eventData->created) ? date('Y-m-d H:i:s', $eventData->created) : '',
        ];

        switch ($eventType) {
            case 'payment_intent.succeeded':
            case 'payment_intent.payment_failed':
                return array_merge($baseData, [
                    'reference' => $eventData->metadata->reference ?? $eventData->id,
                    'amount' => isset($eventData->amount) ? $eventData->amount / 100 : 0,
                    'currency' => $eventData->currency ?? '',
                    'status' => $eventData->status ?? '',
                    'customer_email' => $eventData->receipt_email ?? '',
                    'metadata' => (array) ($eventData->metadata ?? []),
                    'last_payment_error' => $eventData->last_payment_error ?? null,
                ]);

            case 'charge.dispute.created':
                return array_merge($baseData, [
                    'charge_id' => $eventData->charge ?? '',
                    'amount' => isset($eventData->amount) ? $eventData->amount / 100 : 0,
                    'currency' => $eventData->currency ?? '',
                    'reason' => $eventData->reason ?? '',
                    'status' => $eventData->status ?? '',
                ]);

            case 'invoice.payment_succeeded':
                return array_merge($baseData, [
                    'subscription_id' => $eventData->subscription ?? '',
                    'customer_id' => $eventData->customer ?? '',
                    'amount' => isset($eventData->amount_paid) ? $eventData->amount_paid / 100 : 0,
                    'currency' => $eventData->currency ?? '',
                    'status' => $eventData->status ?? '',
                ]);

            default:
                return $baseData;
        }
    }

    protected function getSignatureFromRequest(Request $request): string
    {
        return $request->header('stripe-signature', '');
    }

    protected function calculateExpectedSignature(string $payload): string
    {
        // Stripe handles signature verification internally
        return '';
    }

    protected function extractFailureReason(array $data): string
    {
        if (isset($data['last_payment_error']['message'])) {
            return $data['last_payment_error']['message'];
        }

        return parent::extractFailureReason($data);
    }
}
