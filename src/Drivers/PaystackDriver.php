<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\DisbursementException;
use NexusPay\PaymentMadeEasy\Exceptions\SubscriptionException;

class PaystackDriver extends AbstractPaymentDriver implements
    SubscriptionDriverInterface,
    DisbursementDriverInterface,
    VirtualAccountDriverInterface,
    PaymentLinkDriverInterface
{
    public function initializePayment(array $data): array
    {
        $payload = [
            'email' => $data['email'],
            'amount' => $this->convertAmount($data['amount']),
            'reference' => $data['reference'] ?? $this->generateReference('paystack'),
            'callback_url' => $data['callback_url'] ?? $this->config['callback_url'],
            'metadata' => $data['metadata'] ?? [],
        ];

        if (isset($data['currency'])) {
            $payload['currency'] = $data['currency'];
        }

        if (isset($data['bearer'])) {
            $payload['bearer'] = $data['bearer'];
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/transaction/initialize', [
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
        $response = $this->makeRequest('GET', $this->config['base_url'] . '/transaction/verify/' . $reference, [
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
        $payload = ['transaction' => $reference];

        if ($amount) {
            $payload['amount'] = $this->convertAmount($amount);
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/refund', [
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
        $url = $this->config['base_url'] . '/transaction' . ($queryParams ? '?' . $queryParams : '');

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
            'daily'       => 'daily',
            'weekly'      => 'weekly',
            'monthly'     => 'monthly',
            'quarterly'   => 'quarterly',
            'biannually'  => 'biannually',
            'annually'    => 'annually',
        ];

        $payload = [
            'name'     => $data['name'],
            'interval' => $intervalMap[$data['interval']] ?? $data['interval'],
            'amount'   => $this->convertAmount($data['amount']),
        ];

        if (isset($data['description']))   $payload['description']   = $data['description'];
        if (isset($data['currency']))      $payload['currency']      = $data['currency'];
        if (isset($data['trial_days']))    $payload['trial_days']    = (int) $data['trial_days'];
        if (isset($data['invoice_limit'])) $payload['invoice_limit'] = (int) $data['invoice_limit'];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/plan', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to create Paystack plan: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updatePlan(string $planCode, array $data): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/plan/' . $planCode, [
                'headers' => $this->authHeaders(),
                'json'    => $data,
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to update Paystack plan: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPlan(string $planCode): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/plan/' . $planCode, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to fetch Paystack plan: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listPlans(array $params = []): array
    {
        $qs  = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/plan' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to list Paystack plans: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deletePlan(string $planCode): array
    {
        // Paystack does not have a delete plan endpoint; deactivate by setting send_invoices + send_sms to false
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/plan/' . $planCode, [
                'headers' => $this->authHeaders(),
                'json'    => ['send_invoices' => false, 'send_sms' => false],
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to deactivate Paystack plan: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createSubscription(array $data): array
    {
        $payload = [
            'customer'   => $data['customer'],
            'plan'       => $data['plan'],
        ];

        if (isset($data['start_date']))    $payload['start_date']    = $data['start_date'];
        if (isset($data['authorization'])) $payload['authorization'] = $data['authorization'];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/subscription', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to create Paystack subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cancelSubscription(string $subscriptionCode): array
    {
        try {
            $sub = $this->getSubscription($subscriptionCode);
            return $this->makeRequest('POST', $this->config['base_url'] . '/subscription/disable', [
                'headers' => $this->authHeaders(),
                'json'    => [
                    'code'  => $subscriptionCode,
                    'token' => $sub['data']['email_token'] ?? '',
                ],
            ]);
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to cancel Paystack subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function pauseSubscription(string $subscriptionCode): array
    {
        // Paystack has no native pause; disable is the closest equivalent
        return $this->cancelSubscription($subscriptionCode);
    }

    public function resumeSubscription(string $subscriptionCode): array
    {
        try {
            $sub = $this->getSubscription($subscriptionCode);
            return $this->makeRequest('POST', $this->config['base_url'] . '/subscription/enable', [
                'headers' => $this->authHeaders(),
                'json'    => [
                    'code'  => $subscriptionCode,
                    'token' => $sub['data']['email_token'] ?? '',
                ],
            ]);
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to resume Paystack subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getSubscription(string $subscriptionCode): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/subscription/' . $subscriptionCode, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to fetch Paystack subscription: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listSubscriptions(array $params = []): array
    {
        $qs = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/subscription' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new SubscriptionException('Failed to list Paystack subscriptions: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listCustomerSubscriptions(string $customerEmail): array
    {
        return $this->listSubscriptions(['customer' => $customerEmail]);
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function createTransferRecipient(array $data): array
    {
        $payload = [
            'type'           => $data['type'] ?? 'nuban',
            'name'           => $data['name'],
            'account_number' => $data['account_number'],
            'bank_code'      => $data['bank_code'],
            'currency'       => $data['currency'] ?? 'NGN',
        ];

        if (isset($data['description'])) $payload['description'] = $data['description'];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/transferrecipient', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to create Paystack transfer recipient: ' . $e->getMessage(), 0, $e);
        }
    }

    public function transfer(array $data): array
    {
        $payload = [
            'source'    => 'balance',
            'amount'    => $this->convertAmount($data['amount']),
            'recipient' => $data['recipient_code'],
            'reference' => $data['reference'] ?? $this->generateReference('pstk_trx'),
            'reason'    => $data['reason'] ?? '',
        ];

        if (isset($data['currency'])) $payload['currency'] = $data['currency'];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/transfer', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to initiate Paystack transfer: ' . $e->getMessage(), 0, $e);
        }
    }

    public function bulkTransfer(array $transfers): array
    {
        $mapped = array_map(function (array $item): array {
            return [
                'amount'    => $this->convertAmount($item['amount']),
                'recipient' => $item['recipient_code'],
                'reference' => $item['reference'] ?? $this->generateReference('pstk_bulk'),
                'reason'    => $item['reason'] ?? '',
            ];
        }, $transfers);

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/transfer/bulk', [
                'headers' => $this->authHeaders(),
                'json'    => ['currency' => 'NGN', 'source' => 'balance', 'transfers' => $mapped],
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to initiate Paystack bulk transfer: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyTransfer(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transfer/verify/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to verify Paystack transfer: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listTransfers(array $params = []): array
    {
        $qs = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transfer' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to list Paystack transfers: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listBanks(array $filters = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/bank?country=' . strtolower($country), [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to list Paystack banks: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resolveAccountNumber(array $data): array
    {
        $accountNumber = $data['account_number'] ?? '';
        $bankCode      = $data['bank_code'] ?? '';
        try {
            return $this->makeRequest(
                'GET',
                $this->config['base_url'] . '/bank/resolve?account_number=' . $accountNumber . '&bank_code=' . $bankCode,
                ['headers' => $this->authHeaders(false)]
            );
        } catch (\Throwable $e) {
            throw new DisbursementException('Failed to resolve Paystack account number: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // PaymentLinkDriverInterface
    // -------------------------------------------------------------------------

    public function createPaymentLink(array $data): array
    {
        $payload = [
            'name'         => $data['name'],
            'description'  => $data['description'] ?? '',
            'amount'       => isset($data['amount']) ? $this->convertAmount($data['amount']) : null,
            'currency'     => $data['currency'] ?? 'NGN',
            'redirect_url' => $data['callback_url'] ?? $this->config['callback_url'],
        ];

        if ($payload['amount'] === null) unset($payload['amount']);
        if (isset($data['expires_at']))  $payload['expiration_date']  = $data['expires_at'];
        if (isset($data['metadata']))    $payload['custom_fields']    = $data['metadata'];

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/paymentrequest', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create Paystack payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updatePaymentLink(string $linkId, array $data): array
    {
        try {
            return $this->makeRequest('PUT', $this->config['base_url'] . '/paymentrequest/' . $linkId, [
                'headers' => $this->authHeaders(),
                'json'    => $data,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to update Paystack payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPaymentLink(string $linkId): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/paymentrequest/' . $linkId, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to fetch Paystack payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listPaymentLinks(array $params = []): array
    {
        $qs = $params ? '?' . http_build_query($params) : '';
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/paymentrequest' . $qs, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to list Paystack payment links: ' . $e->getMessage(), 0, $e);
        }
    }

    public function disablePaymentLink(string $linkId): array
    {
        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/paymentrequest/deactivate/' . $linkId, [
                'headers' => $this->authHeaders(),
                'json'    => [],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to disable Paystack payment link: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // VirtualAccountDriverInterface
    // -------------------------------------------------------------------------

    public function createVirtualAccount(array $data): array
    {
        try {
            $payload = [
                'email'          => $data['email'],
                'first_name'     => $data['first_name'] ?? (explode(' ', $data['name'] ?? '')[0] ?? ''),
                'last_name'      => $data['last_name']  ?? (explode(' ', $data['name'] ?? '')[1] ?? ''),
                'phone'          => $data['phone']       ?? '',
                'preferred_bank' => $data['preferred_bank'] ?? 'wema-bank',
            ];

            if (isset($data['bvn'])) {
                $payload['bvn'] = $data['bvn'];
            }

            return $this->makeRequest('POST', $this->config['base_url'] . '/dedicated_account', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create Paystack virtual account: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/dedicated_account/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to get Paystack virtual account: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listVirtualAccounts(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/dedicated_account', [
                'headers' => $this->authHeaders(false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to list Paystack virtual accounts: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deactivateVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('DELETE', $this->config['base_url'] . '/dedicated_account/deactivate', [
                'headers' => $this->authHeaders(),
                'json'    => ['dedicated_account_id' => $reference],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to deactivate Paystack virtual account: ' . $e->getMessage(), 0, $e);
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
