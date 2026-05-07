<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\DisbursementException;

/**
 * Remita payment driver.
 *
 * Remita is a Nigerian electronic payment platform owned by SystemSpecs.
 * It is heavily used for government and enterprise collections/payments.
 *
 * Base URL: https://login.remita.net (live) / https://remitademo.net (sandbox)
 * Authentication: API Key + Secret Key → SHA512 hash for each request
 *   Authorization header: remitaConsumerKey={key},remitaConsumerToken={token}
 *   Token = SHA512(merchantId + serviceTypeId + orderId + totalAmount + apiKey)
 *   For generic requests a simplified approach is used (see authHeaders()).
 */
class RemitaDriver extends AbstractPaymentDriver implements DisbursementDriverInterface
{
    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    public function initializePayment(array $data): array
    {
        $reference = $data['reference'] ?? $this->generateReference('remita');
        $amount    = (string) $data['amount'];

        $payload = [
            'serviceTypeId'   => $data['service_type_id'] ?? $this->config['service_type_id'],
            'amount'          => $amount,
            'orderId'         => $reference,
            'payerName'       => $data['name'] ?? '',
            'payerEmail'      => $data['email'],
            'payerPhone'      => $data['phone'] ?? '',
            'description'     => $data['description'] ?? 'Payment',
            'returnSuccessUrl' => $data['callback_url'] ?? $this->config['callback_url'],
            'returnFailureUrl' => $data['failure_url'] ?? $this->config['callback_url'],
            'customFields'    => $data['metadata'] ?? [],
        ];

        try {
            $response = $this->makeRequest('POST', $this->config['base_url'] . '/payment/start', [
                'headers' => $this->authHeaders($reference, $amount, true),
                'json'    => $payload,
            ]);

            // Remita returns an RRR (Remita Retrieval Reference) which is used
            // to construct the redirect URL for the hosted payment page.
            if (!empty($response['RRR'])) {
                $response['authorization_url'] = $this->config['checkout_url'] . '?RRR=' . $response['RRR'];
            }

            return $response;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Remita initializePayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyPayment(string $reference): array
    {
        // $reference here is the RRR (Remita Retrieval Reference)
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment/query/' . $reference, [
                'headers' => $this->authHeaders($reference, '0', false),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Remita verifyPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        try {
            $payload = ['RRR' => $reference];
            if ($amount !== null) {
                $payload['amount'] = (string) $amount;
            }
            return $this->makeRequest('POST', $this->config['base_url'] . '/payment/refund', [
                'headers' => $this->authHeaders($reference, (string) ($amount ?? 0), true),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Remita refundPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTransactions(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment/search', [
                'headers' => $this->authHeaders('', '0', false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Remita getTransactions failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        try {
            $reference = $data['reference'] ?? $this->generateReference('remita-txfr');
            $amount    = (string) $data['amount'];

            $payload = [
                'batchRef'    => $reference,
                'narration'   => $data['reason'] ?? 'Transfer',
                'sourceAccount' => $this->config['source_account'] ?? '',
                'bankCode'    => $data['bank_code'],
                'beneficiaryBankCode' => $data['bank_code'],
                'beneficiaryAccountNumber' => $data['account_number'],
                'beneficiaryName' => $data['recipient_name'] ?? $data['name'] ?? '',
                'amount'      => $amount,
                'currency'    => $data['currency'] ?? 'NGN',
            ];

            return $this->makeRequest('POST', $this->config['base_url'] . '/payment/initiate/transfer', [
                'headers' => $this->authHeaders($reference, $amount, true),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Remita transfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function bulkTransfer(array $data): array
    {
        try {
            $batchRef = $data['batch_reference'] ?? $this->generateReference('remita-bulk');
            $payload  = [
                'batchRef'    => $batchRef,
                'narration'   => $data['narration'] ?? 'Bulk Transfer',
                'sourceAccount' => $this->config['source_account'] ?? '',
                'transactions' => array_map(fn($t) => [
                    'narration'                 => $t['reason'] ?? 'Transfer',
                    'bankCode'                  => $t['bank_code'],
                    'beneficiaryBankCode'        => $t['bank_code'],
                    'beneficiaryAccountNumber'   => $t['account_number'],
                    'beneficiaryName'            => $t['name'] ?? '',
                    'amount'                    => (string) $t['amount'],
                    'currency'                  => $t['currency'] ?? 'NGN',
                    'uniqueIdentifier'          => $t['reference'] ?? $this->generateReference('remita-b'),
                ], $data['transfers']),
            ];

            return $this->makeRequest('POST', $this->config['base_url'] . '/payment/initiate/bulktransfer', [
                'headers' => $this->authHeaders($batchRef, '0', true),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Remita bulkTransfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyTransfer(string $reference): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment/transfer/status/' . $reference, [
                'headers' => $this->authHeaders($reference, '0', false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Remita verifyTransfer failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function listTransfers(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment/transfer/list', [
                'headers' => $this->authHeaders('', '0', false),
                'query'   => $params,
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Remita listTransfers failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createTransferRecipient(array $data): array
    {
        return [
            'status'  => 'success',
            'message' => 'Remita does not require pre-created recipients.',
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
            return $this->makeRequest('GET', $this->config['base_url'] . '/payment/banklist', [
                'headers' => $this->authHeaders('', '0', false),
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Remita listBanks failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function resolveAccountNumber(array $data): array
    {
        try {
            return $this->makeRequest('POST', $this->config['base_url'] . '/payment/account/lookup', [
                'headers' => $this->authHeaders('', '0', true),
                'json'    => [
                    'accountNo'  => $data['account_number'],
                    'bankCode'   => $data['bank_code'],
                ],
            ]);
        } catch (\Throwable $e) {
            throw new DisbursementException('Remita resolveAccountNumber failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build Remita authorization headers.
     *
     * Token = SHA512(merchantId + serviceTypeId + orderId + amount + apiKey)
     */
    private function authHeaders(string $orderId, string $amount, bool $withContentType = true): array
    {
        $merchantId    = $this->config['merchant_id'];
        $serviceTypeId = $this->config['service_type_id'];
        $apiKey        = $this->config['api_key'];

        $token = hash('sha512', $merchantId . $serviceTypeId . $orderId . $amount . $apiKey);

        $headers = [
            'Authorization' => "remitaConsumerKey={$apiKey},remitaConsumerToken={$token}",
        ];

        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}
