<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\DisbursementException;

/**
 * Budpay payment driver.
 *
 * Budpay is a Nigerian fintech payment gateway with support for card payments,
 * bank transfers, virtual accounts, and payouts.
 *
 * Base URL: https://api.budpay.com/api/v2
 * Authentication: Bearer token (secret key). Some endpoints require an
 *   additional HMAC-SHA512 Encryption header for mutation requests.
 */
class BudpayDriver extends AbstractPaymentDriver implements
    DisbursementDriverInterface,
    VirtualAccountDriverInterface
{
    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    public function initializePayment(array $data): array
    {
        $reference = $data['reference'] ?? $this->generateReference('budpay');

        $payload = [
            'email'        => $data['email'],
            'amount'       => (string) $data['amount'],
            'currency'     => $data['currency'] ?? 'NGN',
            'reference'    => $reference,
            'callback'     => $data['callback_url'] ?? $this->config['callback_url'],
        ];

        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }
        if (isset($data['metadata'])) {
            $payload['metadata'] = $data['metadata'];
        }

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/transaction/initialize', [
                'headers' => $this->authHeaders($reference),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay initializePayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transaction/verify/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay verifyPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        try {
            $payload = ['reference' => $reference];
            if ($amount !== null) {
                $payload['amount'] = (string) $amount;
            }
            return $this->makeRequest('POST', $this->config['base_url'] . '/refund', [
                'headers' => $this->authHeaders($reference),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay refundPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTransactions(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transaction', [
                'headers' => $this->authHeaders(false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay getTransactions failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        try {
            $reference = $data['reference'] ?? $this->generateReference('budpay-txfr');
            $payload   = [
                'currency'       => $data['currency'] ?? 'NGN',
                'amount'         => (string) $data['amount'],
                'bank_code'      => $data['bank_code'],
                'bank_name'      => $data['bank_name'] ?? '',
                'account_number' => $data['account_number'],
                'account_name'   => $data['recipient_name'] ?? $data['name'] ?? '',
                'narration'      => $data['reason'] ?? 'Transfer',
                'reference'      => $reference,
            ];

            return $this->makeRequest('POST', $this->config['base_url'] . '/payout', [
                'headers' => $this->authHeaders($reference),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Budpay transfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function bulkTransfer(array $data): array
    {
        try {
            $batchRef = $data['batch_reference'] ?? $this->generateReference('budpay-bulk');
            $payload  = [
                'currency' => $data['currency'] ?? 'NGN',
                'payouts'  => array_map(fn($t) => [
                    'amount'         => (string) $t['amount'],
                    'bank_code'      => $t['bank_code'],
                    'bank_name'      => $t['bank_name'] ?? '',
                    'account_number' => $t['account_number'],
                    'account_name'   => $t['name'] ?? '',
                    'narration'      => $t['reason'] ?? 'Transfer',
                    'reference'      => $t['reference'] ?? $this->generateReference('budpay-b'),
                ], $data['transfers']),
            ];

            return $this->makeRequest('POST', $this->config['base_url'] . '/bulk_payout', [
                'headers' => $this->authHeaders($batchRef),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Budpay bulkTransfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyTransfer(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payout/query/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Budpay verifyTransfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listTransfers(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payout', [
                'headers' => $this->authHeaders(false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Budpay listTransfers failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createTransferRecipient(array $data): array
    {
        return [
            'status'  => 'success',
            'message' => 'Budpay does not require pre-created recipients.',
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
            return $this->makeRequest('GET', $this->config['base_url'] . '/bank_list', [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Budpay listBanks failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resolveAccountNumber(array $data): array
    {
        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/account/validate', [
                'headers' => $this->authHeaders('validate'),
                'json'    => [
                    'bank_code'      => $data['bank_code'],
                    'account_number' => $data['account_number'],
                    'currency'       => $data['currency'] ?? 'NGN',
                ],
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Budpay resolveAccountNumber failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // VirtualAccountDriverInterface
    // -------------------------------------------------------------------------

    public function createVirtualAccount(array $data): array
    {
        try {
            $payload = [
                'customer' => $data['email'],
                'firstName' => $data['first_name'] ?? (explode(' ', $data['name'] ?? '')[0] ?? ''),
                'lastName' => $data['last_name']  ?? (explode(' ', $data['name'] ?? '')[1] ?? ''),
                'phone'    => $data['phone'] ?? '',
                'amount'   => isset($data['amount']) ? (string) $data['amount'] : null,
                'reference' => $data['reference'] ?? $this->generateReference('budpay-va'),
            ];

            if ($payload['amount'] === null) {
                unset($payload['amount']);
            }

            return $this->makeRequest('POST', $this->config['base_url'] . '/create/virtual_account', [
                'headers' => $this->authHeaders($payload['reference']),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay createVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/virtual_account/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay getVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listVirtualAccounts(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/virtual_account', [
                'headers' => $this->authHeaders(false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay listVirtualAccounts failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deactivateVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('DELETE', $this->config['base_url'] . '/virtual_account/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Budpay deactivateVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build Budpay auth headers.
     *
     * Mutation requests additionally require an Encryption header:
     *   HMAC-SHA512(secret_key + ":" + reference)
     *
     * @param  string|false $reference  Pass reference for POST requests; false for GET.
     */
    private function authHeaders(string|false $reference, bool $withContentType = true): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->config['secret_key']];

        if ($reference !== false) {
            $headers['Encryption'] = hash_hmac('sha512', $this->config['secret_key'] . ':' . $reference, $this->config['secret_key']);
        }

        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}
