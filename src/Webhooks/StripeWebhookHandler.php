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
            'id'      => $eventData->id ?? '',
            'object'  => $eventData->object ?? '',
            'created' => isset($eventData->created) ? date('Y-m-d H:i:s', $eventData->created) : '',
        ];

        switch (true) {
            case in_array($eventType, ['payment_intent.succeeded', 'payment_intent.payment_failed']):
                return array_merge($baseData, [
                    'reference'          => $eventData->metadata->reference ?? $eventData->id,
                    'amount'             => isset($eventData->amount) ? $eventData->amount / 100 : 0,
                    'currency'           => $eventData->currency ?? '',
                    'status'             => $eventData->status ?? '',
                    'customer_email'     => $eventData->receipt_email ?? '',
                    'metadata'           => (array) ($eventData->metadata ?? []),
                    'last_payment_error' => isset($eventData->last_payment_error)
                        ? (array) $eventData->last_payment_error
                        : null,
                ]);

            case str_starts_with($eventType, 'customer.subscription.'):
                return array_merge($baseData, [
                    'subscription_code' => $eventData->id ?? '',
                    'plan_code'         => $eventData->items->data[0]->price->id ?? '',
                    'customer_id'       => $eventData->customer ?? '',
                    'status'            => $eventData->status ?? '',
                    'amount'            => isset($eventData->items->data[0]->price->unit_amount)
                        ? $eventData->items->data[0]->price->unit_amount / 100
                        : 0,
                    'currency'          => $eventData->items->data[0]->price->currency ?? '',
                    'current_period_end' => isset($eventData->current_period_end)
                        ? date('Y-m-d H:i:s', $eventData->current_period_end)
                        : '',
                ]);

            case str_starts_with($eventType, 'invoice.'):
                return array_merge($baseData, [
                    'subscription_code' => $eventData->subscription ?? '',
                    'customer_id'       => $eventData->customer ?? '',
                    'amount'            => isset($eventData->amount_paid) ? $eventData->amount_paid / 100 : 0,
                    'currency'          => $eventData->currency ?? '',
                    'status'            => $eventData->status ?? '',
                ]);

            case str_starts_with($eventType, 'charge.dispute.'):
                return array_merge($baseData, [
                    'reference'      => $eventData->charge ?? '',
                    'amount'         => isset($eventData->amount) ? $eventData->amount / 100 : 0,
                    'currency'       => $eventData->currency ?? '',
                    'reason'         => $eventData->reason ?? '',
                    'status'         => $eventData->status ?? '',
                ]);

            case str_starts_with($eventType, 'payout.'):
                return array_merge($baseData, [
                    'transfer_code' => $eventData->id ?? '',
                    'reference'     => $eventData->id ?? '',
                    'amount'        => isset($eventData->amount) ? $eventData->amount / 100 : 0,
                    'currency'      => $eventData->currency ?? '',
                    'status'        => $eventData->status ?? '',
                    'arrival_date'  => isset($eventData->arrival_date)
                        ? date('Y-m-d', $eventData->arrival_date)
                        : '',
                ]);

            case str_starts_with($eventType, 'radar.early_fraud_warning.'):
                return array_merge($baseData, [
                    'reference'    => $eventData->charge ?? '',
                    'fraud_type'   => $eventData->fraud_type ?? '',
                    'action_taken' => $eventData->actionable ?? false,
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
