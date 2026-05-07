<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;

/**
 * Razorpay payment driver (India).
 *
 * Base URL: https://api.razorpay.com/v1
 * Authentication: HTTP Basic Auth (key_id:key_secret) on every request.
 * Amount convention: paise (1 INR = 100 paise). convertAmount() handles this.
 *
 * Payment flow:
 *   1. POST /orders — creates a Razorpay Order, returns order_id.
 *   2. Client-side: open Razorpay checkout JS, customer pays.
 *   3. Verify: POST /payments/{payment_id}/capture or check signature.
 *   4. Webhook delivers payment.captured / order.paid events.
 */
class RazorpayDriver extends AbstractPaymentDriver implements
    SubscriptionDriverInterface,
    DisbursementDriverInterface,
    PaymentLinkDriverInterface
{
    private function authHeaders(bool $withContentType = true): array
    {
        $encoded = base64_encode($this->config['key_id'] . ':' . $this->config['key_secret']);
        $headers = ['Authorization' => 'Basic ' . $encoded];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    private function baseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://api.razorpay.com/v1', '/');
    }

    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    public function initializePayment(array $data): array
    {
        $reference = $data['reference'] ?? $this->generateReference('rzp');
        $currency  = strtoupper($data['currency'] ?? 'INR');
        $amount    = $this->convertAmount($data['amount']); // paise

        $payload = [
            'amount'          => $amount,
            'currency'        => $currency,
            'receipt'         => $reference,
            'partial_payment' => false,
        ];

        if (isset($data['notes'])) {
            $payload['notes'] = $data['notes'];
        } elseif (isset($data['metadata'])) {
            $payload['notes'] = $data['metadata'];
        }

        $response = $this->makeRequest('POST', $this->baseUrl() . '/orders', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);

        // Attach checkout key and callback so client knows how to open the widget
        $response['data']['key_id']       = $this->config['key_id'];
        $response['data']['callback_url'] = $data['callback_url'] ?? $this->config['callback_url'] ?? '';
        $response['data']['email']        = $data['email'] ?? '';
        $response['data']['name']         = $data['name'] ?? '';
        $response['data']['phone']        = $data['phone'] ?? '';

        return $response;
    }

    public function verifyPayment(string $reference): array
    {
        // $reference is the Razorpay order_id
        return $this->makeRequest('GET', $this->baseUrl() . '/orders/' . $reference, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function getPayment(string $reference): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/payments/' . $reference, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        // $reference is the payment_id
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $this->convertAmount($amount);
        }

        return $this->makeRequest('POST', $this->baseUrl() . '/payments/' . $reference . '/refund', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function getTransactions(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'count' => $filters['count'] ?? $filters['per_page'] ?? 20,
            'skip'  => $filters['skip'] ?? 0,
            'from'  => $filters['from'] ?? null,
            'to'    => $filters['to'] ?? null,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/payments?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    // -------------------------------------------------------------------------
    // SubscriptionDriverInterface
    // -------------------------------------------------------------------------

    public function createPlan(array $data): array
    {
        $intervalMap = [
            'daily'     => ['period' => 'daily',    'interval' => 1],
            'weekly'    => ['period' => 'weekly',   'interval' => 1],
            'monthly'   => ['period' => 'monthly',  'interval' => 1],
            'quarterly' => ['period' => 'monthly',  'interval' => 3],
            'annually'  => ['period' => 'yearly',   'interval' => 1],
            'yearly'    => ['period' => 'yearly',   'interval' => 1],
        ];
        $iv = $intervalMap[strtolower($data['interval'] ?? 'monthly')] ?? $intervalMap['monthly'];

        return $this->makeRequest('POST', $this->baseUrl() . '/plans', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'period'   => $iv['period'],
                'interval' => $iv['interval'],
                'item'     => [
                    'name'     => $data['name'],
                    'amount'   => $this->convertAmount($data['amount']),
                    'currency' => strtoupper($data['currency'] ?? 'INR'),
                    'description' => $data['description'] ?? $data['name'],
                ],
            ],
        ]);
    }

    public function updatePlan(string $planId, array $data): array
    {
        return ['status' => false, 'message' => 'Razorpay plans cannot be updated after creation'];
    }

    public function getPlan(string $planId): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/plans/' . $planId, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listPlans(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'count' => $filters['count'] ?? $filters['per_page'] ?? 20,
            'skip'  => $filters['skip'] ?? 0,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/plans?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function deletePlan(string $planId): array
    {
        return ['status' => false, 'message' => 'Razorpay plans cannot be deleted via API'];
    }

    public function createSubscription(array $data): array
    {
        $payload = [
            'plan_id'       => $data['plan_code'],
            'total_count'   => $data['total_count'] ?? 12,
            'quantity'      => $data['quantity'] ?? 1,
            'notify_info'   => [
                'notify_phone' => $data['phone'] ?? '',
                'notify_email' => $data['email'] ?? '',
            ],
        ];

        if (isset($data['start_date'])) {
            $payload['start_at'] = is_numeric($data['start_date']) ? (int) $data['start_date'] : strtotime($data['start_date']);
        }

        if (isset($data['addons'])) {
            $payload['addons'] = $data['addons'];
        }

        return $this->makeRequest('POST', $this->baseUrl() . '/subscriptions', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/subscriptions/' . $subscriptionId . '/cancel', [
            'headers' => $this->authHeaders(),
            'json'    => ['cancel_at_cycle_end' => 0],
        ]);
    }

    public function pauseSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/subscriptions/' . $subscriptionId . '/pause', [
            'headers' => $this->authHeaders(),
            'json'    => ['pause_at' => 'now'],
        ]);
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/subscriptions/' . $subscriptionId . '/resume', [
            'headers' => $this->authHeaders(),
            'json'    => ['resume_at' => 'now'],
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
            'plan_id' => $filters['plan'] ?? $filters['plan_id'] ?? null,
            'count'   => $filters['count'] ?? $filters['per_page'] ?? 20,
            'skip'    => $filters['skip'] ?? 0,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/subscriptions?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listCustomerSubscriptions(string $customerEmail): array
    {
        return $this->listSubscriptions(['email' => $customerEmail]);
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        $payload = [
            'account_number' => $data['fund_account_id'] ?? $data['account_number'] ?? '',
            'amount'         => $this->convertAmount($data['amount']),
            'currency'       => strtoupper($data['currency'] ?? 'INR'),
            'purpose'        => $data['purpose'] ?? 'payout',
            'narration'      => $data['narration'] ?? $data['reason'] ?? 'Payout',
            'reference_id'   => $data['reference'] ?? $this->generateReference('rzp_payout'),
            'queue_if_low_balance' => $data['queue_if_low_balance'] ?? true,
        ];

        if (isset($data['mode'])) {
            $payload['mode'] = $data['mode']; // NEFT | RTGS | IMPS | UPI
        }

        return $this->makeRequest('POST', $this->baseUrl() . '/payouts', [
            'headers' => array_merge($this->authHeaders(), ['X-Payout-Idempotency' => $data['reference'] ?? $payload['reference_id']]),
            'json'    => $payload,
        ]);
    }

    public function bulkTransfer(array $data): array
    {
        $results = [];
        foreach ($data['transfers'] ?? [] as $item) {
            $results[] = $this->transfer($item);
        }
        return ['status' => true, 'data' => $results];
    }

    public function verifyTransfer(string $reference): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/payouts/' . $reference, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listTransfers(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'count'          => $filters['count'] ?? $filters['per_page'] ?? 20,
            'skip'           => $filters['skip'] ?? 0,
            'account_number' => $filters['account_number'] ?? $this->config['account_number'] ?? null,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/payouts?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function createTransferRecipient(array $data): array
    {
        // Razorpay calls these "Fund Accounts" linked to a Contact
        $contact = $this->makeRequest('POST', $this->baseUrl() . '/contacts', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'name'         => $data['name'],
                'email'        => $data['email'] ?? null,
                'contact'      => $data['phone'] ?? null,
                'type'         => $data['type'] ?? 'vendor',
                'reference_id' => $data['reference'] ?? null,
            ],
        ]);
        $contactId = $contact['id'] ?? '';

        $fundAccount = $this->makeRequest('POST', $this->baseUrl() . '/fund_accounts', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'contact_id'     => $contactId,
                'account_type'   => 'bank_account',
                'bank_account'   => [
                    'name'           => $data['name'],
                    'ifsc'           => $data['ifsc'] ?? $data['bank_code'] ?? '',
                    'account_number' => $data['account_number'],
                ],
            ],
        ]);

        return array_merge($fundAccount, ['data' => ['recipient_code' => $fundAccount['id'] ?? '']]);
    }

    public function listBanks(array $filters = []): array
    {
        return ['status' => true, 'data' => [], 'message' => 'Razorpay uses IFSC codes; consult RBI for a full bank list'];
    }

    public function resolveAccountNumber(array $data): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/fund_accounts/validations', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'account_number' => $this->config['account_number'] ?? '',
                'fund_account'   => [
                    'account_type' => 'bank_account',
                    'bank_account' => [
                        'name'           => $data['name'] ?? '',
                        'ifsc'           => $data['ifsc'] ?? $data['bank_code'] ?? '',
                        'account_number' => $data['account_number'],
                    ],
                    'contact' => [
                        'name' => $data['name'] ?? '',
                    ],
                ],
                'amount'   => 100,
                'currency' => 'INR',
                'receipt'  => $this->generateReference('validate'),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PaymentLinkDriverInterface
    // -------------------------------------------------------------------------

    public function createPaymentLink(array $data): array
    {
        $currency = strtoupper($data['currency'] ?? 'INR');
        $amount   = $this->convertAmount($data['amount']);

        $payload = [
            'amount'      => $amount,
            'currency'    => $currency,
            'description' => $data['description'] ?? $data['name'] ?? 'Payment',
            'customer'    => [
                'name'    => $data['customer_name'] ?? $data['name'] ?? '',
                'email'   => $data['email'] ?? '',
                'contact' => $data['phone'] ?? '',
            ],
            'reminder_enable' => $data['reminder_enable'] ?? true,
            'callback_url'    => $data['callback_url'] ?? $this->config['callback_url'] ?? '',
            'callback_method' => 'get',
        ];

        if (isset($data['expire_by'])) {
            $payload['expire_by'] = is_numeric($data['expire_by']) ? (int) $data['expire_by'] : strtotime($data['expire_by']);
        }

        return $this->makeRequest('POST', $this->baseUrl() . '/payment_links', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function updatePaymentLink(string $id, array $data): array
    {
        $payload = array_filter([
            'description'    => $data['description'] ?? null,
            'reference_id'   => $data['reference'] ?? null,
            'expire_by'      => isset($data['expire_by']) ? (is_numeric($data['expire_by']) ? (int)$data['expire_by'] : strtotime($data['expire_by'])) : null,
            'reminder_enable' => $data['reminder_enable'] ?? null,
            'notes'          => $data['notes'] ?? null,
        ]);

        return $this->makeRequest('PATCH', $this->baseUrl() . '/payment_links/' . $id, [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    public function getPaymentLink(string $id): array
    {
        return $this->makeRequest('GET', $this->baseUrl() . '/payment_links/' . $id, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function listPaymentLinks(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'count'   => $filters['count'] ?? $filters['per_page'] ?? 20,
            'skip'    => $filters['skip'] ?? 0,
            'payment_id' => $filters['payment_id'] ?? null,
        ]));

        return $this->makeRequest('GET', $this->baseUrl() . '/payment_links?' . $query, [
            'headers' => $this->authHeaders(false),
        ]);
    }

    public function disablePaymentLink(string $id): array
    {
        return $this->makeRequest('POST', $this->baseUrl() . '/payment_links/' . $id . '/cancel', [
            'headers' => $this->authHeaders(),
            'json'    => [],
        ]);
    }
}
