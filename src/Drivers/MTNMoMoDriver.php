<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;

/**
 * MTN Mobile Money (MoMo) payment driver.
 *
 * Uses the MTN MoMo Open API (https://momodeveloper.mtn.com).
 * The API exposes two products relevant here:
 *   - Collections  — request payment from a mobile wallet (RequestToPay)
 *   - Disbursements — send money to a mobile wallet (Transfer)
 *
 * Authentication:
 *   Each product has its own API User / API Key pair.
 *   POST /collection/token/   (Basic Auth: api_user:api_key)  → Bearer token
 *   POST /disbursement/token/ (Basic Auth: api_user:api_key)  → Bearer token
 *
 * Amounts are in major currency units (e.g. XOF, GHS, UGX, EUR in sandbox).
 * Transactions are referenced by a UUID (X-Reference-Id header on create).
 */
class MTNMoMoDriver extends AbstractPaymentDriver implements DisbursementDriverInterface
{
    private ?string $collectionToken    = null;
    private int     $collectionExpiry   = 0;
    private ?string $disbursementToken  = null;
    private int     $disbursementExpiry = 0;

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    private function collectionToken(): string
    {
        if ($this->collectionToken && time() < $this->collectionExpiry) {
            return $this->collectionToken;
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/collection/token/', [
            'auth'    => [$this->config['collection_user_id'], $this->config['collection_api_key']],
            'headers' => ['Ocp-Apim-Subscription-Key' => $this->config['collection_subscription_key']],
        ]);

        $this->collectionToken  = $response['access_token'];
        $this->collectionExpiry = time() + (int) ($response['expires_in'] ?? 3600) - 60;

        return $this->collectionToken;
    }

    private function disbursementToken(): string
    {
        if ($this->disbursementToken && time() < $this->disbursementExpiry) {
            return $this->disbursementToken;
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/disbursement/token/', [
            'auth'    => [$this->config['disbursement_user_id'], $this->config['disbursement_api_key']],
            'headers' => ['Ocp-Apim-Subscription-Key' => $this->config['disbursement_subscription_key']],
        ]);

        $this->disbursementToken  = $response['access_token'];
        $this->disbursementExpiry = time() + (int) ($response['expires_in'] ?? 3600) - 60;

        return $this->disbursementToken;
    }

    private function collectionHeaders(bool $withContentType = true, ?string $referenceId = null): array
    {
        $headers = [
            'Authorization'               => 'Bearer ' . $this->collectionToken(),
            'Ocp-Apim-Subscription-Key'   => $this->config['collection_subscription_key'],
            'X-Target-Environment'        => $this->config['environment'] ?? 'sandbox',
        ];
        if ($referenceId) {
            $headers['X-Reference-Id'] = $referenceId;
        }
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    private function disbursementHeaders(bool $withContentType = true, ?string $referenceId = null): array
    {
        $headers = [
            'Authorization'               => 'Bearer ' . $this->disbursementToken(),
            'Ocp-Apim-Subscription-Key'   => $this->config['disbursement_subscription_key'],
            'X-Target-Environment'        => $this->config['environment'] ?? 'sandbox',
        ];
        if ($referenceId) {
            $headers['X-Reference-Id'] = $referenceId;
        }
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    /**
     * Request to Pay (Collections) — sends a push notification to customer's wallet.
     *
     * Required:
     *   phone  — MSISDN in international format (no +), e.g. "256XXXXXXXXX"
     *   amount — amount in major currency units
     *
     * Returns the X-Reference-Id UUID which you use to poll verifyPayment().
     * MoMo does not redirect; payment is asynchronous.
     */
    public function initializePayment(array $data): array
    {
        $referenceId = $data['reference'] ?? $this->generateUuid();
        $currency    = strtoupper($data['currency'] ?? $this->config['currency'] ?? 'EUR');
        $phone       = ltrim($data['phone'] ?? '', '+');

        $payload = [
            'amount'     => (string) $data['amount'],
            'currency'   => $currency,
            'externalId' => $data['external_id'] ?? $referenceId,
            'payer'      => [
                'partyIdType' => 'MSISDN',
                'partyId'     => $phone,
            ],
            'payerMessage' => $data['description'] ?? 'Payment',
            'payeeNote'    => $data['note'] ?? $data['description'] ?? 'Payment',
        ];

        if (isset($data['callback_url'])) {
            $payload['callbackUrl'] = $data['callback_url'];
        }

        $this->makeRequest('POST', $this->config['base_url'] . '/collection/v1_0/requesttopay', [
            'headers' => $this->collectionHeaders(true, $referenceId),
            'json'    => $payload,
        ]);

        // MoMo returns 202 Accepted with empty body — we return the reference
        return [
            'status'  => true,
            'message' => 'Request to Pay initiated',
            'data'    => [
                'reference'    => $referenceId,
                'status'       => 'PENDING',
            ],
        ];
    }

    public function verifyPayment(string $reference): array
    {
        return $this->makeRequest('GET', $this->config['base_url'] . '/collection/v1_0/requesttopay/' . $reference, [
            'headers' => $this->collectionHeaders(false),
        ]);
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        // MoMo Collections does not have a direct refund; use Refunds API if available
        $referenceId = $this->generateUuid();
        $currency    = strtoupper($this->config['currency'] ?? 'EUR');

        $payload = [
            'amount'            => (string) ($amount ?? 0),
            'currency'          => $currency,
            'externalId'        => $this->generateUuid(),
            'payerMessage'      => 'Refund',
            'payeeNote'         => 'Refund for ' . $reference,
            'referenceIdToRefund' => $reference,
        ];

        $this->makeRequest('POST', $this->config['base_url'] . '/collection/v1_0/refund', [
            'headers' => $this->collectionHeaders(true, $referenceId),
            'json'    => $payload,
        ]);

        return [
            'status'  => true,
            'message' => 'Refund initiated',
            'data'    => ['reference' => $referenceId],
        ];
    }

    public function getTransactions(array $filters = []): array
    {
        // MoMo has no list-transactions endpoint; return account balance as proxy
        return $this->makeRequest('GET', $this->config['base_url'] . '/collection/v1_0/account/balance', [
            'headers' => $this->collectionHeaders(false),
        ]);
    }

    // -------------------------------------------------------------------------
    // DisbursementDriverInterface
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        $referenceId = $data['reference'] ?? $this->generateUuid();
        $currency    = strtoupper($data['currency'] ?? $this->config['currency'] ?? 'EUR');
        $phone       = ltrim($data['phone'] ?? $data['msisdn'] ?? '', '+');

        $payload = [
            'amount'      => (string) $data['amount'],
            'currency'    => $currency,
            'externalId'  => $data['external_id'] ?? $referenceId,
            'payee'       => [
                'partyIdType' => 'MSISDN',
                'partyId'     => $phone,
            ],
            'payerMessage' => $data['narration'] ?? $data['reason'] ?? 'Transfer',
            'payeeNote'    => $data['narration'] ?? $data['reason'] ?? 'Transfer',
        ];

        if (isset($data['callback_url'])) {
            $payload['callbackUrl'] = $data['callback_url'];
        }

        $this->makeRequest('POST', $this->config['base_url'] . '/disbursement/v1_0/transfer', [
            'headers' => $this->disbursementHeaders(true, $referenceId),
            'json'    => $payload,
        ]);

        return [
            'status'  => true,
            'message' => 'Transfer initiated',
            'data'    => ['reference' => $referenceId, 'status' => 'PENDING'],
        ];
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
        return $this->makeRequest('GET', $this->config['base_url'] . '/disbursement/v1_0/transfer/' . $reference, [
            'headers' => $this->disbursementHeaders(false),
        ]);
    }

    public function listTransfers(array $filters = []): array
    {
        return $this->makeRequest('GET', $this->config['base_url'] . '/disbursement/v1_0/account/balance', [
            'headers' => $this->disbursementHeaders(false),
        ]);
    }

    public function createTransferRecipient(array $data): array
    {
        return [
            'status'  => true,
            'message' => 'MTN MoMo uses MSISDN directly; no recipient pre-creation needed.',
            'data'    => ['recipient_code' => $data['phone'] ?? $data['msisdn'] ?? ''],
        ];
    }

    public function listBanks(array $filters = []): array
    {
        return ['status' => true, 'data' => [], 'message' => 'MTN MoMo uses mobile numbers (MSISDN), not bank codes'];
    }

    public function resolveAccountNumber(array $data): array
    {
        $phone = ltrim($data['account_number'] ?? $data['phone'] ?? $data['msisdn'] ?? '', '+');
        return $this->makeRequest('GET', $this->config['base_url'] . '/collection/v1_0/accountholder/msisdn/' . $phone . '/basicuserinfo', [
            'headers' => $this->collectionHeaders(false),
        ]);
    }
}
