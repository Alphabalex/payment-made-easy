<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;
use NexusPay\PaymentMadeEasy\Exceptions\SubscriptionException;

class StripeDriver extends AbstractPaymentDriver implements
    SubscriptionDriverInterface,
    PaymentLinkDriverInterface
{
    protected $stripe;

    public function __construct(array $config)
    {
        if (!class_exists(StripeClient::class)) {
            throw new PaymentException(
                'The Stripe driver requires stripe/stripe-php. Install with: composer require stripe/stripe-php:^17.4'
            );
        }
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

    // -------------------------------------------------------------------------
    // SubscriptionDriverInterface
    // -------------------------------------------------------------------------

    public function createPlan(array $data): array
    {
        $intervalMap = [
            'daily'      => ['interval' => 'day',   'interval_count' => 1],
            'weekly'     => ['interval' => 'week',  'interval_count' => 1],
            'monthly'    => ['interval' => 'month', 'interval_count' => 1],
            'quarterly'  => ['interval' => 'month', 'interval_count' => 3],
            'biannually' => ['interval' => 'month', 'interval_count' => 6],
            'annually'   => ['interval' => 'year',  'interval_count' => 1],
        ];

        $billing = $intervalMap[$data['interval']] ?? ['interval' => 'month', 'interval_count' => 1];

        try {
            // Stripe requires a Product to be created first
            $product = $this->stripe->products->create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? $data['name'],
            ]);

            $priceData = [
                'product'        => $product->id,
                'unit_amount'    => $this->convertAmount($data['amount']),
                'currency'       => strtolower($data['currency'] ?? 'usd'),
                'recurring'      => [
                    'interval'       => $billing['interval'],
                    'interval_count' => $billing['interval_count'],
                ],
            ];

            if (isset($data['invoice_limit']) && $data['invoice_limit'] > 0) {
                $priceData['recurring']['usage_type'] = 'licensed';
            }

            if (isset($data['trial_days'])) {
                $priceData['recurring']['trial_period_days'] = (int) $data['trial_days'];
            }

            $price = $this->stripe->prices->create($priceData);

            return [
                'status' => true,
                'data'   => [
                    'plan_code'  => $price->id,
                    'product_id' => $product->id,
                    'name'       => $data['name'],
                    'amount'     => $data['amount'],
                    'currency'   => strtolower($data['currency'] ?? 'usd'),
                    'interval'   => $data['interval'],
                    'raw'        => ['price' => $price->toArray(), 'product' => $product->toArray()],
                ],
            ];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to create Stripe plan: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function updatePlan(string $planCode, array $data): array
    {
        // Stripe Prices are immutable; only the Product (metadata/name) can be updated
        try {
            $price = $this->stripe->prices->retrieve($planCode);
            if (isset($data['name']) || isset($data['description'])) {
                $productUpdate = [];
                if (isset($data['name']))        $productUpdate['name']        = $data['name'];
                if (isset($data['description'])) $productUpdate['description'] = $data['description'];
                $this->stripe->products->update($price->product, $productUpdate);
            }
            // Archive / activate the price if active flag provided
            if (isset($data['active'])) {
                $this->stripe->prices->update($planCode, ['active' => (bool) $data['active']]);
            }
            return ['status' => true, 'data' => ['plan_code' => $planCode]];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to update Stripe plan: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getPlan(string $planCode): array
    {
        try {
            $price = $this->stripe->prices->retrieve($planCode, ['expand' => ['product']]);
            return ['status' => true, 'data' => $price->toArray()];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to fetch Stripe plan: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listPlans(array $params = []): array
    {
        try {
            $prices = $this->stripe->prices->all(array_merge(['expand' => ['data.product']], $params));
            return ['status' => true, 'data' => $prices->data];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to list Stripe plans: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function deletePlan(string $planCode): array
    {
        try {
            $updated = $this->stripe->prices->update($planCode, ['active' => false]);
            return ['status' => true, 'data' => $updated->toArray()];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to deactivate Stripe plan: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function createSubscription(array $data): array
    {
        try {
            // Ensure the customer exists in Stripe
            $customers = $this->stripe->customers->all(['email' => $data['customer'], 'limit' => 1]);
            if (count($customers->data) > 0) {
                $customer = $customers->data[0];
            } else {
                $customer = $this->stripe->customers->create(['email' => $data['customer']]);
            }

            $subData = [
                'customer' => $customer->id,
                'items'    => [['price' => $data['plan']]],
            ];

            if (isset($data['start_date'])) {
                $subData['billing_cycle_anchor'] = strtotime($data['start_date']);
            }

            if (isset($data['authorization'])) {
                $subData['default_payment_method'] = $data['authorization'];
            }

            $subscription = $this->stripe->subscriptions->create($subData);

            return [
                'status' => true,
                'data'   => [
                    'subscription_code' => $subscription->id,
                    'customer_id'       => $customer->id,
                    'status'            => $subscription->status,
                    'raw'               => $subscription->toArray(),
                ],
            ];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to create Stripe subscription: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function cancelSubscription(string $subscriptionCode): array
    {
        try {
            $sub = $this->stripe->subscriptions->cancel($subscriptionCode);
            return ['status' => true, 'data' => $sub->toArray()];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to cancel Stripe subscription: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function pauseSubscription(string $subscriptionCode): array
    {
        try {
            $sub = $this->stripe->subscriptions->update($subscriptionCode, [
                'pause_collection' => ['behavior' => 'void'],
            ]);
            return ['status' => true, 'data' => $sub->toArray()];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to pause Stripe subscription: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function resumeSubscription(string $subscriptionCode): array
    {
        try {
            $sub = $this->stripe->subscriptions->update($subscriptionCode, [
                'pause_collection' => '',
            ]);
            return ['status' => true, 'data' => $sub->toArray()];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to resume Stripe subscription: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getSubscription(string $subscriptionCode): array
    {
        try {
            $sub = $this->stripe->subscriptions->retrieve($subscriptionCode);
            return ['status' => true, 'data' => $sub->toArray()];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to fetch Stripe subscription: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listSubscriptions(array $params = []): array
    {
        try {
            $subs = $this->stripe->subscriptions->all($params);
            return ['status' => true, 'data' => $subs->data];
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to list Stripe subscriptions: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listCustomerSubscriptions(string $customerEmail): array
    {
        try {
            $customers = $this->stripe->customers->all(['email' => $customerEmail, 'limit' => 1]);
            if (empty($customers->data)) {
                return ['status' => true, 'data' => []];
            }
            return $this->listSubscriptions(['customer' => $customers->data[0]->id]);
        } catch (ApiErrorException $e) {
            throw new SubscriptionException('Failed to list Stripe customer subscriptions: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    // -------------------------------------------------------------------------
    // PaymentLinkDriverInterface
    // -------------------------------------------------------------------------

    public function createPaymentLink(array $data): array
    {
        try {
            $payload = [
                'line_items' => [[
                    'price'    => $data['price_id'] ?? null,
                    'quantity' => $data['quantity'] ?? 1,
                ]],
            ];

            // If no price_id, create an ad-hoc price
            if (empty($payload['line_items'][0]['price'])) {
                $price = $this->stripe->prices->create([
                    'unit_amount' => $this->convertAmount($data['amount'] ?? 0),
                    'currency'    => strtolower($data['currency'] ?? 'usd'),
                    'product_data' => ['name' => $data['name']],
                ]);
                $payload['line_items'][0]['price'] = $price->id;
            }

            if (isset($data['callback_url'])) {
                $payload['after_completion'] = ['type' => 'redirect', 'redirect' => ['url' => $data['callback_url']]];
            }

            if (isset($data['expires_at'])) {
                $payload['expires_at'] = strtotime($data['expires_at']);
                $payload['active']     = true;
            }

            $link = $this->stripe->paymentLinks->create($payload);

            return [
                'status' => true,
                'data'   => [
                    'link_id' => $link->id,
                    'url'     => $link->url,
                    'raw'     => $link->toArray(),
                ],
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Failed to create Stripe payment link: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function updatePaymentLink(string $linkId, array $data): array
    {
        try {
            $update = [];
            if (isset($data['active'])) $update['active'] = (bool) $data['active'];
            $link = $this->stripe->paymentLinks->update($linkId, $update);
            return ['status' => true, 'data' => $link->toArray()];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Failed to update Stripe payment link: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getPaymentLink(string $linkId): array
    {
        try {
            $link = $this->stripe->paymentLinks->retrieve($linkId);
            return ['status' => true, 'data' => $link->toArray()];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Failed to fetch Stripe payment link: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listPaymentLinks(array $params = []): array
    {
        try {
            $links = $this->stripe->paymentLinks->all($params);
            return ['status' => true, 'data' => $links->data];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException('Failed to list Stripe payment links: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function disablePaymentLink(string $linkId): array
    {
        return $this->updatePaymentLink($linkId, ['active' => false]);
    }
}
