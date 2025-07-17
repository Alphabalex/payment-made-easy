<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;

class StripeDriver extends AbstractPaymentDriver
{
    protected $stripe;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->stripe = new StripeClient($this->config['secret_key']);
    }

    public function initializePayment(array $data): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $this->convertAmount($data['amount']),
                'currency' => strtolower($data['currency'] ?? 'usd'),
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'reference' => $data['reference'] ?? $this->generateReference('stripe'),
                ]),
                'receipt_email' => $data['email'],
                'description' => $data['description'] ?? 'Payment for services',
            ]);

            return [
                'status' => true,
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'reference' => $paymentIntent->metadata->reference,
                ],
            ];
        } catch (ApiErrorException $e) {
            throw new PaymentException("Stripe payment initialization failed: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($reference);

            return [
                'status' => true,
                'data' => [
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'reference' => $paymentIntent->metadata->reference ?? $reference,
                    'payment_intent' => $paymentIntent,
                ],
            ];
        } catch (ApiErrorException $e) {
            throw new PaymentException("Stripe payment verification failed: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        try {
            $refundData = ['payment_intent' => $reference];
            
            if ($amount) {
                $refundData['amount'] = $this->convertAmount($amount);
            }

            $refund = $this->stripe->refunds->create($refundData);

            return [
                'status' => true,
                'data' => [
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount / 100,
                    'status' => $refund->status,
                ],
            ];
        } catch (ApiErrorException $e) {
            throw new PaymentException("Stripe refund failed: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getTransactions(array $params = []): array
    {
        try {
            $charges = $this->stripe->charges->all($params);

            return [
                'status' => true,
                'data' => $charges->data,
            ];
        } catch (ApiErrorException $e) {
            throw new PaymentException("Failed to retrieve Stripe transactions: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}