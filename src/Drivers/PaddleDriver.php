<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;

/**
 * Paddle payment driver.
 *
 * Paddle is a Merchant of Record (MoR) for SaaS / digital goods.
 * This driver targets the Paddle Billing API (v1 — the modern API).
 *
 * Base URL: https://api.paddle.com (live) / https://sandbox-api.paddle.com (sandbox)
 * Authentication: API Key in `Authorization: Bearer {api_key}` header.
 *
 * Paddle Billing concepts:
 *   Product  → what you sell
 *   Price    → amount/billing interval attached to a Product (maps to "plan")
 *   Transaction → one-time charge (maps to "payment")
 *   Subscription → recurring charge
 *   Payment Link → shareable URL for checkout (Hosted Checkout)
 *
 * Amounts: Paddle uses minor units (cents/paise) but as strings (e.g. "2999").
 * convertAmount() handles the major → minor conversion.
 */
class PaddleDriver extends AbstractPaymentDriver implements
    SubscriptionDriverInterface,
    PaymentLinkDriverInterface
{
    private function authHeaders(bool $withContentType = true): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->config['api_key']];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    private function baseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://api.paddle.com', '/');
    }

    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    /**
     * Create a Paddle Transaction (one-time payment).
     *
     * Required:
     *   items — array of [price_id, quantity] OR [price.amount, price.currency_code, price.product_id]
     *
     * Returns a transaction with a checkout.url for hosted payment.
     */
    public function initializePayment(array $data): array
    {
        $currency  = strtoupper($data['currency'] ?? 'USD');
        $reference = $data['reference'] ?? $this->generateReference('paddle');

        $items = $data['items'] ?? [];
        if (empty($items) && isset($data['price_id'])) {
            $items = [['price_id' => $data['price_id'], 'quantity' => $data['quantity'] ?? 1]];
        }
        // If no price_id provided, create an ad-hoc price inline
        if (empty($items) && isset($data['amount'])) {
            $items = [[
                'quantity' => 1,
                'price'    => [
                    'description'   => $data['description'] ?? 'Payment',
                    'unit_price'    => ['amount' => (string) $this->convertAmount($data['amount']), 'currency_code' => $currency],
                    'product'       => ['name' => $data['name'] ?? 'Product', 'tax_category' => 'standard'],
                ],
            ]];
        }

        $payload = [
            'items'          => $items,
            'customer_id'    => $data['customer_id'] ?? null,
            'custom_data'    => $data['metadata'] ?? null,
            'checkout'       => [
                'url' => $data['callback_url'] ?? $this->config['callback_url'] ?? null,
            ],
            'currency_code'  => $currency,
        ];

        if (isset($data['email']) && !isset($data['customer_id'])) {
            $payload['customer'] = ['email' => $data['email']];
        }

        $payload = array_filter($payload);

        $response = $this->makeRequest('POST', $this->baseUrl() . '/transactions', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);

        if (isset($response['data']['checkout']['url'])) {
            $response['data']['authorization_url'] = $response['data']['checkout']['url'];
        }

        return $response;
    }

    public function verifyPayment(string $reference): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/transactions/' . $reference, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        // $reference is the transaction_id; we adjust the first line item
        $payload = [
            'transaction_id' => $reference,
            'reason'         => 'customer_request',
        ];

        if ($amount !== null) {
            $payload['type']   = 'partial';
            $payload['amount'] = (string) $this->convertAmount($amount);
        } else {
            $payload['type'] = 'full';
        }

        return $this->makeRequest('POST', $this->baseUrl() . '/adjustments', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function getTransactions(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'per_page'     => $filters['per_page'] ?? 20,
            'after'        => $filters['after'] ?? null,
            'customer_id'  => $filters['customer_id'] ?? null,
            'status'       => $filters['status'] ?? null,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/transactions?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    // -------------------------------------------------------------------------
    // SubscriptionDriverInterface
    // -------------------------------------------------------------------------

    public function createPlan(array $data): array
    {
        // Step 1: create a Product if not provided
        $productId = $data['product_id'] ?? null;
        if (!$productId) {
            $product = $this->makeRequest('POST', $this->baseUrl() . '/products', [
                'headers' => $this->authHeaders(),
                'json'    => [
                    'name'         => $data['name'],
                    'description'  => $data['description'] ?? $data['name'],
                    'tax_category' => $data['tax_category'] ?? 'standard',
                ],
            ]);
            $productId = $product['data']['id'];
        }

        // Step 2: create a Price (= plan)
        $intervalMap = [
            'daily'     => ['interval' => 'day',   'frequency' => 1],
            'weekly'    => ['interval' => 'week',  'frequency' => 1],
            'monthly'   => ['interval' => 'month', 'frequency' => 1],
            'quarterly' => ['interval' => 'month', 'frequency' => 3],
            'annually'  => ['interval' => 'year',  'frequency' => 1],
            'yearly'    => ['interval' => 'year',  'frequency' => 1],
        ];
        $iv       = $intervalMap[strtolower($data['interval'] ?? 'monthly')] ?? $intervalMap['monthly'];
        $currency = strtoupper($data['currency'] ?? 'USD');

        return $this->makeRequest('POST', $this->baseUrl() . '/prices', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'product_id'    => $productId,
                'description'   => $data['description'] ?? $data['name'],
                'billing_cycle' => ['frequency' => $iv['frequency'], 'interval' => $iv['interval']],
                'unit_price'    => ['amount' => (string) $this->convertAmount($data['amount']), 'currency_code' => $currency],
            ],
        ]);
    }

    public function updatePlan(string $planId, array $data): array
    {
        $payload = array_filter([
            'description' => $data['description'] ?? $data['name'] ?? null,
            'unit_price'  => isset($data['amount']) ? ['amount' => (string) $this->convertAmount($data['amount']), 'currency_code' => strtoupper($data['currency'] ?? 'USD')] : null,
        ]);

        return $this->makeRequest('PATCH', $this->baseUrl() . '/prices/' . $planId, [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function getPlan(string $planId): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/prices/' . $planId, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listPlans(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'per_page'   => $filters['per_page'] ?? 20,
            'product_id' => $filters['product_id'] ?? null,
            'status'     => $filters['status'] ?? 'active',
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/prices?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function deletePlan(string $planId): array
    {
        return $this->makeRequest('PATCH', $this->baseUrl() . '/prices/' . $planId, [
            'headers' => $this->authHeaders(),
            'json'    => ['status' => 'archived'],
        ]);
    }

    public function createSubscription(array $data): array
    {
        // Paddle Billing subscriptions are created via checkout (hosted page).
        // Create a transaction with subscription items and return the checkout URL.
        return $this->initializePayment($data);
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/subscriptions/' . $subscriptionId . '/cancel', [
            'headers' => $this->authHeaders(),
            'json'    => ['effective_from' => 'next_billing_period'],
        ]);
    }

    public function pauseSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/subscriptions/' . $subscriptionId . '/pause', [
            'headers' => $this->authHeaders(),
            'json'    => ['effective_from' => 'next_billing_period'],
        ]);
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/subscriptions/' . $subscriptionId . '/resume', [
            'headers' => $this->authHeaders(),
            'json'    => ['effective_from' => 'immediately'],
        ]);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/subscriptions/' . $subscriptionId, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listSubscriptions(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'per_page'    => $filters['per_page'] ?? 20,
            'after'       => $filters['after'] ?? null,
            'customer_id' => $filters['customer_id'] ?? null,
            'price_id'    => $filters['plan'] ?? $filters['plan_id'] ?? null,
            'status'      => $filters['status'] ?? null,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/subscriptions?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listCustomerSubscriptions(string $customerEmail): array
    {
        // Paddle requires customer_id not email; we do a customer search first
        $customers = $this->makeRequest('GET', $this->baseUrl() . '/customers?search=' . urlencode($customerEmail), [
            'headers' => $this->authHeaders(false),
        ]);
        $customerId = $customers['data'][0]['id'] ?? null;
        if (!$customerId) {
            return ['status' => true, 'data' => []];
        }
        return $this->listSubscriptions(['customer_id' => $customerId]);
    }

    // -------------------------------------------------------------------------
    // PaymentLinkDriverInterface
    // -------------------------------------------------------------------------

    public function createPaymentLink(array $data): array
    {
        $currency = strtoupper($data['currency'] ?? 'USD');
        $items    = $data['items'] ?? [];
        if (empty($items) && isset($data['price_id'])) {
            $items = [['price_id' => $data['price_id'], 'quantity' => 1]];
        }
        if (empty($items) && isset($data['amount'])) {
            $items = [[
                'quantity' => 1,
                'price'    => [
                    'description' => $data['description'] ?? $data['name'] ?? 'Payment',
                    'unit_price'  => ['amount' => (string) $this->convertAmount($data['amount']), 'currency_code' => $currency],
                    'product'     => ['name' => $data['name'] ?? 'Product', 'tax_category' => 'standard'],
                ],
            ]];
        }

        return $this->makeRequest('POST', $this->baseUrl() . '/payment-links', [
            'headers' => $this->authHeaders(),
            'json'    => array_filter([
                'items'       => $items,
                'description' => $data['description'] ?? $data['name'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'custom_data' => $data['metadata'] ?? null,
            ]),
        ]);
    }

    public function updatePaymentLink(string $id, array $data): array
    {
        return $this->makeRequest('PATCH', $this->baseUrl() . '/payment-links/' . $id, [
            'headers' => $this->authHeaders(),
            'json'    => array_filter([
                'description' => $data['description'] ?? $data['name'] ?? null,
                'custom_data' => $data['metadata'] ?? null,
            ]),
        ]);
    }

    public function getPaymentLink(string $id): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/payment-links/' . $id, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listPaymentLinks(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'per_page' => $filters['per_page'] ?? 20,
            'after'    => $filters['after'] ?? null,
            'status'   => $filters['status'] ?? null,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/payment-links?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function disablePaymentLink(string $id): array
    {
        return $this->makeRequest('PATCH', $this->baseUrl() . '/payment-links/' . $id, [
            'headers' => $this->authHeaders(),
            'json'    => ['status' => 'archived'],
        ]);
    }
}
