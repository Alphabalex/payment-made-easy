<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\DisbursementException;
use NexusPay\PaymentMadeEasy\Exceptions\SubscriptionException;

class FlutterwaveDriver extends AbstractPaymentDriver implements
    SubscriptionDriverInterface,
    DisbursementDriverInterface,
    VirtualAccountDriverInterface,
    PaymentLinkDriverInterface
{
    public function initializePayment(array $data): array
    {
        $payload = [
            'tx_ref' => $data['reference'] ?? $this->generateReference('flw'),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'redirect_url' => $data['callback_url'] ?? $this->config['callback_url'],
            'customer' => [
                'email' => $data['email'],
                'name' => $data['name'] ?? '',
                'phonenumber' => $data['phone'] ?? '',
            ],
            'customizations' => [
                'title' => $data['title'] ?? 'Payment',
                'description' => $data['description'] ?? 'Payment for services',
            ],
            'meta' => $data['metadata'] ?? [],
        ];

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $response;
    }

    public function verifyPayment(string $reference): array
    {
        $response = $this->makeRequest('GET', $this->config['base_url'] . '/transactions/' . $reference . '/verify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
            ],
        ]);

        return $response;
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        $payload = ['id' => $reference];

        if ($amount) {
            $payload['amount'] = $amount;
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/transactions/' . $reference . '/refund', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $response;
    }

    public function getTransactions(array $params = []): array
    {
        $queryParams = http_build_query($params);
        $url = $this->config['base_url'] . '/transactions' . ($queryParams ? '?' . $queryParams : '');

        $response = $this->makeRequest('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
            ],
        ]);

        return $response;
    }

    // -------------------------------------------------------------------------
    // SubscriptionDriverInterface
    // -------------------------------------------------------------------------

    public function createPlan(array $data): array
    {
        $intervalMap = [
            'daily'      => 'daily',
            'weekly'     => 'weekly',
            'monthly'    => 'monthly',
            'quarterly'  => 'quarterly',
            'biannually' => 'bi-annually',
            'annually'   => 'yearly',
        ];

        $payload = [
            'name'     => $data['name'],
            'amount'   => $data['amount'],
            'interval' => $intervalMap[$data['interval']] ?? $data['interval'],
            'currency' => $data['currency'] ?? 'NGN',
        ];

        if (isset($data['duration']))    $payload['duration']    = (int) $data['duration']; // Flutterwave uses duration for invoice_limit
        if (isset($data['invoice_limit'])) $payload['duration']  = (int) $data['invoice_limit'];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/payment-plans', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to create Flutterwave plan: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updatePlan(string $planCode, array $data): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/payment-plans/' . $planCode, [
                'headers' => $this->authHeaders(),
                'json'    => $data,
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to update Flutterwave plan: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPlan(string $planCode): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment-plans/' . $planCode, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to fetch Flutterwave plan: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listPlans(array $params = []): array
    {
        $qs = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment-plans' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to list Flutterwave plans: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deletePlan(string $planCode): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/payment-plans/' . $planCode . '/cancel', [
                'headers' => $this->authHeaders(),
                'json'    => [],
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to cancel Flutterwave plan: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a subscription by initializing a payment with a payment_plan attached.
     * The customer completes the standard checkout; Flutterwave auto-enrolls them.
     */
    public function createSubscription(array $data): array
    {
        $payload = [
            'tx_ref'       => $data['reference'] ?? $this->generateReference('flw_sub'),
            'amount'       => $data['amount'] ?? 0,
            'currency'     => $data['currency'] ?? 'NGN',
            'redirect_url' => $data['callback_url'] ?? $this->config['callback_url'],
            'payment_plan' => $data['plan'],
            'customer'     => [
                'email'       => $data['customer'],
                'name'        => $data['customer_name'] ?? '',
                'phonenumber' => $data['customer_phone'] ?? '',
            ],
        ];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/payments', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to create Flutterwave subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cancelSubscription(string $subscriptionCode): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/subscriptions/' . $subscriptionCode . '/cancel', [
                'headers' => $this->authHeaders(),
                'json'    => [],
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to cancel Flutterwave subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function pauseSubscription(string $subscriptionCode): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/subscriptions/' . $subscriptionCode . '/cancel', [
                'headers' => $this->authHeaders(),
                'json'    => [],
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to pause Flutterwave subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resumeSubscription(string $subscriptionCode): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/subscriptions/' . $subscriptionCode . '/activate', [
                'headers' => $this->authHeaders(),
                'json'    => [],
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to resume Flutterwave subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getSubscription(string $subscriptionCode): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/subscriptions/' . $subscriptionCode, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to fetch Flutterwave subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listSubscriptions(array $params = []): array
    {
        $qs = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/subscriptions' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to list Flutterwave subscriptions: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listCustomerSubscriptions(string $customerEmail): array
    {
        return $this->listSubscriptions(['email' => $customerEmail]);
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function createTransferRecipient(array $data): array
    {
        // Flutterwave does not have a separate recipient creation step;
        // account details are included directly in each transfer.
        // Return a normalized stub so callers use the account_number as recipient_code.
        return [
            'status' => true,
            'data'   => [
                'recipient_code' => $data['account_number'],
                'account_number' => $data['account_number'],
                'bank_code'      => $data['bank_code'],
                'name'           => $data['name'],
                'currency'       => $data['currency'] ?? 'NGN',
            ],
        ];
    }

    public function transfer(array $data): array
    {
        $payload = [
            'account_bank'    => $data['bank_code'],
            'account_number'  => $data['recipient_code'], // recipient_code == account_number for Flutterwave
            'amount'          => $data['amount'],
            'currency'        => $data['currency'] ?? 'NGN',
            'reference'       => $data['reference'] ?? $this->generateReference('flw_trx'),
            'narration'       => $data['reason'] ?? '',
            'debit_currency'  => $data['currency'] ?? 'NGN',
        ];

        if (isset($data['beneficiary_name'])) $payload['beneficiary_name'] = $data['beneficiary_name'];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/transfers', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to initiate Flutterwave transfer: ' . $e->getMessage(), 0, $e);
        }
    }

    public function bulkTransfer(array $transfers): array
    {
        $mapped = array_map(function (array $item): array {
            return [
                'account_bank'   => $item['bank_code'],
                'account_number' => $item['recipient_code'],
                'amount'         => $item['amount'],
                'currency'       => $item['currency'] ?? 'NGN',
                'reference'      => $item['reference'] ?? $this->generateReference('flw_bulk'),
                'narration'      => $item['reason'] ?? '',
            ];
        }, $transfers);

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/bulk-transfers', [
                'headers' => $this->authHeaders(),
                'json'    => ['title' => 'Bulk Transfer', 'bulk_data' => $mapped],
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to initiate Flutterwave bulk transfer: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyTransfer(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transfers/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to verify Flutterwave transfer: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listTransfers(array $params = []): array
    {
        $qs = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transfers' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to list Flutterwave transfers: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listBanks(array $filters = []): array
    {
        $country = strtoupper($filters['country'] ?? 'NG');
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/banks/' . $country, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to list Flutterwave banks: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resolveAccountNumber(array $data): array
    {
        $accountNumber = $data['account_number'] ?? '';
        $bankCode      = $data['bank_code'] ?? $data['account_bank'] ?? '';
        try {
            return $this->makeRequest(
                'GET',
                $this->config['base_url'] . '/accounts/resolve?account_number=' . $accountNumber . '&account_bank=' . $bankCode,
                ['headers' => $this->authHeaders(false)]
            );
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to resolve Flutterwave account number: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // PaymentLinkDriverInterface
    // -------------------------------------------------------------------------

    public function createPaymentLink(array $data): array
    {
        $payload = [
            'name'         => $data['name'],
            'amount'       => $data['amount'] ?? 0,
            'currency'     => $data['currency'] ?? 'NGN',
            'redirect_url' => $data['callback_url'] ?? $this->config['callback_url'],
        ];

        if (isset($data['description']))  $payload['description']  = $data['description'];
        if (isset($data['expires_at']))   $payload['expiry']        = $data['expires_at'];
        if (!isset($data['amount']))      $payload['is_permanent']  = true;

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/payment-links', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create Flutterwave payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updatePaymentLink(string $linkId, array $data): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/payment-links/' . $linkId, [
                'headers' => $this->authHeaders(),
                'json'    => $data,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to update Flutterwave payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPaymentLink(string $linkId): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment-links/' . $linkId, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to fetch Flutterwave payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listPaymentLinks(array $params = []): array
    {
        $qs = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment-links' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to list Flutterwave payment links: ' . $e->getMessage(), 0, $e);
        }
    }

    public function disablePaymentLink(string $linkId): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/payment-links/' . $linkId, [
                'headers' => $this->authHeaders(),
                'json'    => ['status' => 'inactive'],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to disable Flutterwave payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // VirtualAccountDriverInterface
    // -------------------------------------------------------------------------

    public function createVirtualAccount(array $data): array
    {
        try {
            $payload = [
                'email'    => $data['email'],
                'is_permanent' => $data['is_permanent'] ?? true,
                'bvn'      => $data['bvn'] ?? '',
                'tx_ref'   => $data['reference'] ?? $this->generateReference('flw-va'),
                'amount'   => $data['amount'] ?? 0,
                'currency' => $data['currency'] ?? 'NGN',
                'narration' => $data['description'] ?? ($data['name'] ?? ''),
            ];

            if (isset($data['frequency'])) {
                $payload['frequency'] = $data['frequency'];
            }

            return $this->makeRequest('POST', $this->config['base_url'] . '/virtual-account-numbers', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create Flutterwave virtual account: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/virtual-account-numbers/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to get Flutterwave virtual account: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listVirtualAccounts(array $params = []): array
    {
        // Flutterwave does not offer a list endpoint for virtual account numbers;
        // return an empty data set to satisfy the interface contract.
        return ['status' => 'success', 'data' => [], 'message' => 'List not supported by Flutterwave VAN API'];
    }

    public function deactivateVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('DELETE', $this->config['base_url'] . '/virtual-account-numbers/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to deactivate Flutterwave virtual account: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authHeaders(bool $withContentType = true): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->config['secret_key']];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }
}
