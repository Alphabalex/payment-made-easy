<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\DisbursementException;
use NexusPay\PaymentMadeEasy\Exceptions\SubscriptionException;

/**
 * Monnify payment driver.
 *
 * Monnify is a Nigerian payment gateway (owned by TeamApt / Moniepoint) that
 * specialises in bank-transfer payments via reserved/dynamic virtual accounts.
 *
 * Base URL: https://api.monnify.com
 * Authentication: Basic Auth (api_key:secret_key) → exchange for bearer token
 *   POST /api/v1/auth/login  → { responseBody: { accessToken } }
 */
class MonnifyDriver extends AbstractPaymentDriver implements
    SubscriptionDriverInterface,
    DisbursementDriverInterface,
    VirtualAccountDriverInterface
{
    private ?string $accessToken = null;

    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    public function initializePayment(array $data): array
    {
        $payload = [
            'amount'             => $data['amount'],
            'customerName'       => $data['name']  ?? '',
            'customerEmail'      => $data['email'],
            'paymentReference'   => $data['reference'] ?? $this->generateReference('monnify'),
            'paymentDescription' => $data['description'] ?? 'Payment',
            'currencyCode'       => $data['currency'] ?? 'NGN',
            'contractCode'       => $this->config['contract_code'],
            'redirectUrl'        => $data['callback_url'] ?? $this->config['callback_url'],
            'paymentMethods'     => $data['payment_methods'] ?? ['CARD', 'ACCOUNT_TRANSFER'],
        ];

        if (isset($data['metadata'])) {
            $payload['metadata'] = $data['metadata'];
        }

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/api/v1/merchant/transactions/init-transaction', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify initializePayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v2/merchant/transactions/query?paymentReference=' . urlencode($reference), [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify verifyPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        try {
            $transactionRef = $this->resolveTransactionRef($reference);
            $payload = [
                'transactionReference' => $transactionRef,
                'refundReason'         => 'Customer request',
                'customerNote'         => 'Refund processed',
                'refundAmount'         => $amount,
            ];
            if ($amount === null) {
                unset($payload['refundAmount']);
            }
            return $this->makeRequest('POST', $this->config['base_url'] . '/api/v1/merchant/transactions/refund', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify refundPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTransactions(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v1/merchant/transactions', [
                'headers' => $this->authHeaders(false),
                'query'   => array_merge(['page' => 0, 'size' => 20], $params),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify getTransactions failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // SubscriptionDriverInterface
    // -------------------------------------------------------------------------

    public function createPlan(array $data): array
    {
        // Monnify does not have a native subscription/plan API. We return a
        // stub so the interface is satisfied and callers can detect support
        // via instanceof, but use a different gateway for subscriptions.
        throw new SubscriptionException('Monnify does not support subscription plans natively.');
    }

    public function updatePlan(string $planCode, array $data): array
    {
        throw new SubscriptionException('Monnify does not support subscription plans natively.');
    }

    public function getPlan(string $planCode): array
    {
        throw new SubscriptionException('Monnify does not support subscription plans natively.');
    }

    public function listPlans(array $params = []): array
    {
        throw new SubscriptionException('Monnify does not support subscription plans natively.');
    }

    public function deletePlan(string $planCode): array
    {
        throw new SubscriptionException('Monnify does not support subscription plans natively.');
    }

    public function createSubscription(array $data): array
    {
        throw new SubscriptionException('Monnify does not support subscriptions natively.');
    }

    public function cancelSubscription(string $subscriptionCode): array
    {
        throw new SubscriptionException('Monnify does not support subscriptions natively.');
    }

    public function pauseSubscription(string $subscriptionCode): array
    {
        throw new SubscriptionException('Monnify does not support subscriptions natively.');
    }

    public function resumeSubscription(string $subscriptionCode): array
    {
        throw new SubscriptionException('Monnify does not support subscriptions natively.');
    }

    public function getSubscription(string $subscriptionCode): array
    {
        throw new SubscriptionException('Monnify does not support subscriptions natively.');
    }

    public function listSubscriptions(array $params = []): array
    {
        throw new SubscriptionException('Monnify does not support subscriptions natively.');
    }

    public function listCustomerSubscriptions(string $customerEmail): array
    {
        throw new SubscriptionException('Monnify does not support subscriptions natively.');
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        try {
            $payload = [
                'amount'              => $data['amount'],
                'reference'           => $data['reference'] ?? $this->generateReference('monnify-txfr'),
                'narration'           => $data['reason'] ?? 'Transfer',
                'destinationBankCode' => $data['bank_code'],
                'destinationAccountNumber' => $data['account_number'],
                'currency'            => $data['currency'] ?? 'NGN',
                'sourceAccountNumber' => $this->config['wallet_account_number'] ?? '',
            ];
            return $this->makeRequest('POST', $this->config['base_url'] . '/api/v2/disbursements/single', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Monnify transfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function bulkTransfer(array $data): array
    {
        try {
            $payload = [
                'title'               => $data['title'] ?? 'Bulk Transfer',
                'batchReference'      => $data['batch_reference'] ?? $this->generateReference('monnify-bulk'),
                'narration'           => $data['narration'] ?? 'Bulk Transfer',
                'sourceAccountNumber' => $this->config['wallet_account_number'] ?? '',
                'onValidationFailure' => $data['on_validation_failure'] ?? 'CONTINUE',
                'notificationInterval' => $data['notification_interval'] ?? 20,
                'transactionList'     => array_map(fn($t) => [
                    'amount'              => $t['amount'],
                    'reference'           => $t['reference'] ?? $this->generateReference('monnify-b'),
                    'narration'           => $t['reason'] ?? 'Transfer',
                    'destinationBankCode' => $t['bank_code'],
                    'destinationAccountNumber' => $t['account_number'],
                    'currency'            => $t['currency'] ?? 'NGN',
                ], $data['transfers']),
            ];
            return $this->makeRequest('POST', $this->config['base_url'] . '/api/v2/disbursements/batch', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Monnify bulkTransfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyTransfer(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v2/disbursements/single/summary?reference=' . urlencode($reference), [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Monnify verifyTransfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listTransfers(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v2/disbursements/single/transactions', [
                'headers' => $this->authHeaders(false),
                'query'   => array_merge(['pageNo' => 0, 'pageSize' => 20], $params),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Monnify listTransfers failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createTransferRecipient(array $data): array
    {
        // Monnify does not maintain a separate recipient registry; account details
        // are passed inline on each transfer. Return normalized data to satisfy interface.
        return [
            'status'  => 'success',
            'message' => 'Monnify does not require pre-created recipients.',
            'data'    => [
                'recipient_code'   => $data['account_number'],
                'account_number'   => $data['account_number'],
                'bank_code'        => $data['bank_code'] ?? '',
                'account_name'     => $data['name'] ?? '',
            ],
        ];
    }

    public function listBanks(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v1/sdk/transactions/banks', [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Monnify listBanks failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resolveAccountNumber(array $data): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v1/disbursements/account/validate', [
                'headers' => $this->authHeaders(false),
                'query'   => [
                    'accountNumber' => $data['account_number'],
                    'bankCode'      => $data['bank_code'],
                ],
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Monnify resolveAccountNumber failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // VirtualAccountDriverInterface
    // -------------------------------------------------------------------------

    public function createVirtualAccount(array $data): array
    {
        try {
            $payload = [
                'accountReference'    => $data['reference'] ?? $this->generateReference('monnify-va'),
                'accountName'         => $data['name'],
                'currencyCode'        => $data['currency'] ?? 'NGN',
                'contractCode'        => $this->config['contract_code'],
                'customerEmail'       => $data['email'],
                'customerName'        => $data['name'],
                'getAllAvailableBanks' => $data['all_banks'] ?? true,
                'preferredBanks'      => $data['preferred_banks'] ?? [],
            ];

            if (isset($data['bvn'])) {
                $payload['bvn'] = $data['bvn'];
            }

            if (isset($data['amount'])) {
                // Monnify supports restricted (fixed-amount) virtual accounts
                $payload['amount']       = $data['amount'];
                $payload['incomeSplitConfig'] = $data['income_split'] ?? [];
            }

            return $this->makeRequest('POST', $this->config['base_url'] . '/api/v2/bank-transfer/reserved-accounts', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify createVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v2/bank-transfer/reserved-accounts/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify getVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listVirtualAccounts(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v1/merchant/transactions/reserved-accounts', [
                'headers' => $this->authHeaders(false),
                'query'   => array_merge(['page' => 0, 'size' => 20], $params),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify listVirtualAccounts failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deactivateVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('DELETE', $this->config['base_url'] . '/api/v1/bank-transfer/reserved-accounts/reference/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Monnify deactivateVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authenticate(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/api/v1/auth/login', [
            'auth'    => [$this->config['api_key'], $this->config['secret_key']],
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [],
        ]);

        $this->accessToken = $response['responseBody']['accessToken']
            ?? throw new \RuntimeException('Monnify authentication failed: no access token in response.');

        return $this->accessToken;
    }

    private function authHeaders(bool $withContentType = true): array
    {
        $token   = $this->authenticate();
        $headers = ['Authorization' => 'Bearer ' . $token];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    /** Monnify uses transactionReference (gateway) not paymentReference for refunds. */
    private function resolveTransactionRef(string $reference): string
    {
        $result = $this->verifyPayment($reference);
        return $result['responseBody']['transactionReference'] ?? $reference;
    }
}
