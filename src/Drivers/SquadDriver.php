<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\DisbursementException;

/**
 * Squad (by GTCo) payment driver.
 *
 * Squad is a Nigerian payment gateway built by Guaranty Trust Company.
 * Base URL: https://api-d.squadco.com (sandbox) / https://api.squadco.com (live)
 * Authentication: Bearer token using the secret key.
 */
class SquadDriver extends AbstractPaymentDriver implements
    DisbursementDriverInterface,
    VirtualAccountDriverInterface
{
    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    public function initializePayment(array $data): array
    {
        $payload = [
            'email'             => $data['email'],
            'amount'            => $this->convertAmount($data['amount']),  // kobo
            'initiate_type'     => $data['initiate_type'] ?? 'inline',
            'currency'          => $data['currency'] ?? 'NGN',
            'transaction_ref'   => $data['reference'] ?? $this->generateReference('squad'),
            'callback_url'      => $data['callback_url'] ?? $this->config['callback_url'],
        ];

        if (isset($data['name'])) {
            $nameParts = explode(' ', $data['name'], 2);
            $payload['customer_name'] = $data['name'];
        }
        if (isset($data['phone'])) {
            $payload['phone_number'] = $data['phone'];
        }
        if (isset($data['metadata'])) {
            $payload['metadata'] = $data['metadata'];
        }

        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/transaction/initiate', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad initializePayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transaction/verify/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad verifyPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        try {
            $payload = ['transaction_ref' => $reference];
            if ($amount !== null) {
                $payload['refund_type'] = 'partial';
                $payload['amount']      = $this->convertAmount($amount);
            } else {
                $payload['refund_type'] = 'full';
            }
            return $this->makeRequest('POST', $this->config['base_url'] . '/transaction/refund', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad refundPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTransactions(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/transaction/query', [
                'headers' => $this->authHeaders(false),
                'query'   => array_merge(['page' => 1, 'perPage' => 20], $params),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad getTransactions failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        try {
            $payload = [
                'transaction_reference' => $data['reference'] ?? $this->generateReference('squad-txfr'),
                'amount'               => $this->convertAmount($data['amount']),
                'bank_code'            => $data['bank_code'],
                'account_number'       => $data['account_number'],
                'account_name'         => $data['recipient_name'] ?? $data['name'] ?? '',
                'currency_id'          => $data['currency'] ?? 'NGN',
                'remark'               => $data['reason'] ?? 'Transfer',
            ];
            return $this->makeRequest('POST', $this->config['base_url'] . '/payout/initiate', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Squad transfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function bulkTransfer(array $data): array
    {
        // Squad does not offer a dedicated bulk-transfer endpoint;
        // execute transfers sequentially and aggregate results.
        $results = [];
        foreach ($data['transfers'] as $transfer) {
            $results[] = $this->transfer($transfer);
        }
        return ['status' => 'success', 'data' => $results];
    }

    public function verifyTransfer(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payout/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Squad verifyTransfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listTransfers(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payout/list', [
                'headers' => $this->authHeaders(false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Squad listTransfers failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createTransferRecipient(array $data): array
    {
        // Squad does not maintain a recipient registry; pass details inline.
        return [
            'status'  => 'success',
            'message' => 'Squad does not require pre-created recipients.',
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
            return $this->makeRequest('GET', $this->config['base_url'] . '/payout/banks', [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Squad listBanks failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resolveAccountNumber(array $data): array
    {
        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/payout/account/lookup', [
                'headers' => $this->authHeaders(),
                'json'    => [
                    'bank_code'      => $data['bank_code'],
                    'account_number' => $data['account_number'],
                ],
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Squad resolveAccountNumber failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // VirtualAccountDriverInterface
    // -------------------------------------------------------------------------

    public function createVirtualAccount(array $data): array
    {
        try {
            $payload = [
                'customer_identifier' => $data['reference'] ?? $this->generateReference('squad-va'),
                'display_name'        => $data['name'],
                'bvn'                 => $data['bvn'] ?? '',
                'first_name'          => $data['first_name'] ?? (explode(' ', $data['name'] ?? '')[0] ?? ''),
                'last_name'           => $data['last_name']  ?? (explode(' ', $data['name'] ?? '')[1] ?? ''),
                'mobile_num'          => $data['phone'] ?? '',
                'email'               => $data['email'],
                'beneficiary_account' => $this->config['beneficiary_account'] ?? '',
            ];

            if (isset($data['amount'])) {
                $payload['amount'] = $this->convertAmount($data['amount']);
            }

            return $this->makeRequest('POST', $this->config['base_url'] . '/virtual-account/create', [
                'headers' => $this->authHeaders(),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad createVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/virtual-account/' . $reference, [
                'headers' => $this->authHeaders(false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad getVirtualAccount failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listVirtualAccounts(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/virtual-account', [
                'headers' => $this->authHeaders(false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad listVirtualAccounts failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deactivateVirtualAccount(string $reference): array
    {
        try {
            return $this->makeRequest('PATCH', $this->config['base_url'] . '/virtual-account/' . $reference, [
                'headers' => $this->authHeaders(),
                'json'    => ['beneficiary_account' => ''],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Squad deactivateVirtualAccount failed: ' . $e->getMessage(), 0, $e);
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
