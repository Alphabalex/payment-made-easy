<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;

/**
 * PayPal payment driver.
 *
 * Uses PayPal REST API v2 for payments and v1 for billing plans / payouts.
 *
 * Base URL: https://api.paypal.com (live) / https://api.sandbox.paypal.com (sandbox)
 * Authentication: OAuth2 client_credentials — Bearer token cached with expiry.
 *   POST /v1/oauth2/token  (Basic Auth: client_id:client_secret)
 *
 * Amount convention: PayPal uses decimal strings (e.g. "50.00"), not subunits.
 * All amounts passed in must be in major currency units (USD, EUR, etc.).
 */
class PayPalDriver extends AbstractPaymentDriver implements
    SubscriptionDriverInterface,
    DisbursementDriverInterface,
    PaymentLinkDriverInterface
{
    private ?string $accessToken    = null;
    private int     $tokenExpiresAt = 0;

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    private function authenticate(): string
    {
        if ($this->accessToken && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/v1/oauth2/token', [
            'auth'        => [$this->config['client_id'], $this->config['client_secret']],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $this->accessToken    = $response['access_token'];
        $this->tokenExpiresAt = time() + ($response['expires_in'] ?? 3600) - 60;

        return $this->accessToken;
    }

    private function authHeaders(bool $withContentType = true): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->authenticate()];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    public function initializePayment(array $data): array
    {
        $reference = $data['reference'] ?? $this->generateReference('paypal');
        $currency  = strtoupper($data['currency'] ?? 'USD');
        $amount    = number_format($data['amount'], 2, '.', '');

        $payload = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'amount'       => ['currency_code' => $currency, 'value' => $amount],
                'description'  => $data['description'] ?? 'Payment',
            ]],
            'application_context' => [
                'return_url' => $data['callback_url'] ?? $this->config['callback_url'],
                'cancel_url' => $data['cancel_url'] ?? $this->config['cancel_url'] ?? ($data['callback_url'] ?? $this->config['callback_url']),
                'brand_name' => $data['brand_name'] ?? config('app.name', 'My Store'),
                'user_action' => 'PAY_NOW',
            ],
        ];

        if (isset($data['metadata'])) {
            $payload['purchase_units'][0]['custom_id'] = json_encode($data['metadata']);
        }

        return $this->makeRequest('POST', $this->config['base_url'] . '/v2/checkout/orders', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function verifyPayment(string $reference): array
    {
        return $this->makeRequest('GET', $this->config['base_url'] . '/v2/checkout/orders/' . $reference, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        // $reference is the PayPal capture ID
        $payload = [];
        if ($amount !== null) {
            $currency = $this->config['currency'] ?? 'USD';
            $payload['amount'] = [
                'value'         => number_format($amount, 2, '.', ''),
                'currency_code' => strtoupper($currency),
            ];
        }

        return $this->makeRequest('POST', $this->config['base_url'] . '/v2/payments/captures/' . $reference . '/refund', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function getTransactions(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'start_date' => $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days')) . 'T00:00:00-0700',
            'end_date'   => $filters['end_date'] ?? date('Y-m-d') . 'T23:59:59-0700',
            'page_size'  => $filters['page_size'] ?? $filters['per_page'] ?? 20,
            'page'       => $filters['page'] ?? 1,
            'fields'     => 'all',
        ]));

        return $this->makeRequest('GET', $this->config['base_url'] . '/v1/reporting/transactions?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    // -------------------------------------------------------------------------
    // SubscriptionDriverInterface
    // -------------------------------------------------------------------------

    public function createPlan(array $data): array
    {
        // Step 1: create a product if not provided
        $productId = $data['product_id'] ?? null;
        if (!$productId) {
            $product = $this->makeRequest('POST', $this->config['base_url'] . '/v1/catalogs/products', [
                'headers' => $this->authHeaders(),
                'json'    => [
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? $data['name'],
                    'type'        => 'SERVICE',
                    'category'    => 'SOFTWARE',
                ],
            ]);
            $productId = $product['id'];
        }

        // Step 2: create billing plan
        $intervalMap = [
            'daily'     => ['interval_unit' => 'DAY',   'interval_count' => 1],
            'weekly'    => ['interval_unit' => 'WEEK',  'interval_count' => 1],
            'monthly'   => ['interval_unit' => 'MONTH', 'interval_count' => 1],
            'quarterly' => ['interval_unit' => 'MONTH', 'interval_count' => 3],
            'annually'  => ['interval_unit' => 'YEAR',  'interval_count' => 1],
            'yearly'    => ['interval_unit' => 'YEAR',  'interval_count' => 1],
        ];
        $iv       = $intervalMap[strtolower($data['interval'] ?? 'monthly')] ?? $intervalMap['monthly'];
        $currency = strtoupper($data['currency'] ?? 'USD');
        $amount   = number_format($data['amount'], 2, '.', '');

        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/billing/plans', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'product_id'  => $productId,
                'name'        => $data['name'],
                'description' => $data['description'] ?? $data['name'],
                'status'      => 'ACTIVE',
                'billing_cycles' => [[
                    'frequency'      => ['interval_unit' => $iv['interval_unit'], 'interval_count' => $iv['interval_count']],
                    'tenure_type'    => 'REGULAR',
                    'sequence'       => 1,
                    'total_cycles'   => 0,
                    'pricing_scheme' => ['fixed_price' => ['value' => $amount, 'currency_code' => $currency]],
                ]],
                'payment_preferences' => ['auto_bill_outstanding' => true, 'payment_failure_threshold' => 3],
            ],
        ]);
    }

    public function updatePlan(string $planId, array $data): array
    {
        return $this->makeRequest('PATCH', $this->config['base_url'] . '/v1/billing/plans/' . $planId, [
            'headers' => $this->authHeaders(),
            'json'    => [['op' => 'replace', 'path' => '/description', 'value' => $data['description'] ?? $data['name'] ?? '']],
        ]);
    }

    public function getPlan(string $planId): array
    {
        return $this->makeRequest('GET', $this->config['base_url'] . '/v1/billing/plans/' . $planId, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listPlans(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'page_size' => $filters['page_size'] ?? $filters['per_page'] ?? 20,
            'page'      => $filters['page'] ?? 1,
            'status'    => $filters['status'] ?? 'ACTIVE',
        ]));

        return $this->makeRequest('GET', $this->config['base_url'] . '/v1/billing/plans?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function deletePlan(string $planId): array
    {
        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/billing/plans/' . $planId . '/deactivate', [
            'headers' => $this->authHeaders(),
            'json'    => [],
        ]);
    }

    public function createSubscription(array $data): array
    {
        $payload = [
            'plan_id'    => $data['plan_code'],
            'subscriber' => [
                'name'          => ['given_name' => $data['first_name'] ?? '', 'surname' => $data['last_name'] ?? ''],
                'email_address' => $data['email'],
            ],
            'application_context' => [
                'return_url' => $data['callback_url'] ?? $this->config['callback_url'],
                'cancel_url' => $data['cancel_url'] ?? $this->config['callback_url'],
            ],
        ];

        if (isset($data['start_date'])) {
            $payload['start_time'] = $data['start_date'];
        }

        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/billing/subscriptions', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/billing/subscriptions/' . $subscriptionId . '/cancel', [
            'headers' => $this->authHeaders(),
            'json'    => ['reason' => 'Customer request'],
        ]);
    }

    public function pauseSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/billing/subscriptions/' . $subscriptionId . '/suspend', [
            'headers' => $this->authHeaders(),
            'json'    => ['reason' => 'Customer request'],
        ]);
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/billing/subscriptions/' . $subscriptionId . '/activate', [
            'headers' => $this->authHeaders(),
            'json'    => ['reason' => 'Customer request'],
        ]);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('GET', $this->config['base_url'] . '/v1/billing/subscriptions/' . $subscriptionId, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listSubscriptions(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'plan_id'   => $filters['plan'] ?? $filters['plan_id'] ?? null,
            'page_size' => $filters['page_size'] ?? $filters['per_page'] ?? 20,
            'page'      => $filters['page'] ?? 1,
        ]));

        return $this->makeRequest('GET', $this->config['base_url'] . '/v1/billing/subscriptions?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listCustomerSubscriptions(string $customerEmail): array
    {
        return ['status' => true, 'data' => [], 'message' => 'Use listSubscriptions() filtered by plan_id; PayPal has no email-based subscription search'];
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        $currency = strtoupper($data['currency'] ?? 'USD');

        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/payments/payouts', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'sender_batch_header' => [
                    'sender_batch_id' => $data['batch_id'] ?? $this->generateReference('batch'),
                    'email_subject'   => $data['email_subject'] ?? 'You have a payment',
                ],
                'items' => [[
                    'recipient_type' => $data['recipient_type'] ?? 'EMAIL',
                    'amount'         => ['value' => number_format($data['amount'], 2, '.', ''), 'currency' => $currency],
                    'receiver'       => $data['receiver'] ?? $data['email'] ?? '',
                    'note'           => $data['reason'] ?? $data['narration'] ?? '',
                    'sender_item_id' => $data['reference'] ?? $this->generateReference('paypal_payout'),
                ]],
            ],
        ]);
    }

    public function bulkTransfer(array $data): array
    {
        $currency = strtoupper($data['currency'] ?? 'USD');
        $items = array_map(function ($t) use ($currency) {
            return [
                'recipient_type' => $t['recipient_type'] ?? 'EMAIL',
                'amount'         => ['value' => number_format($t['amount'], 2, '.', ''), 'currency' => strtoupper($t['currency'] ?? $currency)],
                'receiver'       => $t['receiver'] ?? $t['email'] ?? '',
                'note'           => $t['reason'] ?? $t['narration'] ?? '',
                'sender_item_id' => $t['reference'] ?? $this->generateReference('paypal_payout'),
            ];
        }, $data['transfers'] ?? []);

        return $this->makeRequest('POST', $this->config['base_url'] . '/v1/payments/payouts', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'sender_batch_header' => [
                    'sender_batch_id' => $data['batch_id'] ?? $this->generateReference('batch'),
                    'email_subject'   => $data['email_subject'] ?? 'You have a payment',
                ],
                'items' => $items,
            ],
        ]);
    }

    public function verifyTransfer(string $reference): array
    {
        return $this->makeRequest('GET', $this->config['base_url'] . '/v1/payments/payouts/' . $reference, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listTransfers(array $filters = []): array
    {
        $batchId = $filters['batch_id'] ?? '';
        if ($batchId) {
            return $this->makeRequest('GET', $this->config['base_url'] . '/v1/payments/payouts/' . $batchId, [
                'headers' => $this->authHeaders(false),
            ]);
        }
        return ['status' => true, 'data' => [], 'message' => 'Provide batch_id to retrieve PayPal payout details'];
    }

    public function createTransferRecipient(array $data): array
    {
        return [
            'status'  => true,
            'message' => 'PayPal uses email/PayPal ID directly; no recipient pre-creation needed.',
            'data'    => ['recipient_code' => $data['email'] ?? $data['receiver'] ?? ''],
        ];
    }

    public function listBanks(array $filters = []): array
    {
        return ['status' => true, 'data' => [], 'message' => 'PayPal payouts use email or PayPal ID, not bank codes'];
    }

    public function resolveAccountNumber(array $data): array
    {
        return ['status' => true, 'data' => $data, 'message' => 'PayPal uses email/PayPal ID; no account resolution needed'];
    }

    // -------------------------------------------------------------------------
    // PaymentLinkDriverInterface
    // -------------------------------------------------------------------------

    public function createPaymentLink(array $data): array
    {
        $currency = strtoupper($data['currency'] ?? 'USD');
        $amount   = number_format($data['amount'], 2, '.', '');

        return $this->makeRequest('POST', $this->config['base_url'] . '/v2/checkout/orders', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'intent'         => 'CAPTURE',
                'purchase_units' => [[
                    'amount'      => ['currency_code' => $currency, 'value' => $amount],
                    'description' => $data['description'] ?? $data['name'] ?? 'Payment',
                ]],
                'application_context' => [
                    'return_url' => $data['return_url'] ?? $data['callback_url'] ?? $this->config['callback_url'],
                    'cancel_url' => $data['cancel_url'] ?? $this->config['callback_url'],
                    'user_action' => 'PAY_NOW',
                ],
            ],
        ]);
    }

    public function updatePaymentLink(string $id, array $data): array
    {
        return ['status' => false, 'message' => 'PayPal v2 orders cannot be updated after creation'];
    }

    public function getPaymentLink(string $id): array
    {
        return $this->verifyPayment($id);
    }

    public function listPaymentLinks(array $filters = []): array
    {
        return $this->getTransactions($filters);
    }

    public function disablePaymentLink(string $id): array
    {
        return $this->makeRequest('POST', $this->config['base_url'] . '/v2/checkout/orders/' . $id . '/cancel', [
            'headers' => $this->authHeaders(),
            'json'    => [],
        ]);
    }
}
