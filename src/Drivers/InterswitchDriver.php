<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

/**
 * Interswitch payment driver.
 *
 * Interswitch is a Nigerian fintech giant powering Quickteller, Verve cards,
 * and enterprise collections. The Interswitch Payment Gateway (IPG) uses
 * OAuth2 client-credentials for API authentication.
 *
 * Base URL: https://qa.interswitchng.com (sandbox) / https://api.interswitchgroup.com (live)
 * Authentication: POST /passport/oauth/token  (client_credentials grant)
 *   Client ID & Secret encoded as Basic Auth → returns { access_token }
 */
class InterswitchDriver extends AbstractPaymentDriver
{
    private ?string $accessToken    = null;
    private int     $tokenExpiresAt = 0;

    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    public function initializePayment(array $data): array
    {
        $reference = $data['reference'] ?? $this->generateReference('isw');
        $amount    = $this->convertAmount($data['amount']); // kobo

        $payload = [
            'merchantCode'           => $this->config['merchant_code'],
            'payableCode'            => $this->config['payable_code'],
            'amount'                 => (string) $amount,
            'transactionReference'   => $reference,
            'currencyCode'           => $data['currency'] ?? '566',   // 566 = NGN ISO 4217 numeric
            'customerEmail'          => $data['email'],
            'customerName'           => $data['name'] ?? '',
            'customerMobile'         => $data['phone'] ?? '',
            'redirectUrl'            => $data['callback_url'] ?? $this->config['callback_url'],
        ];

        if (isset($data['metadata'])) {
            $payload['customData'] = $data['metadata'];
        }

        try {
            $response = $this->makeRequest('POST', $this->config['base_url'] . '/api/v3/purchases', [
                'headers' => $this->authHeaders(true),
                'json'    => $payload,
            ]);

            // Construct hosted-page URL from the returned paymentUrl or use default
            if (empty($response['paymentUrl'])) {
                $response['paymentUrl'] = $this->config['checkout_url']
                    . '?merchantCode=' . $this->config['merchant_code']
                    . '&payableCode=' . $this->config['payable_code']
                    . '&amount=' . $amount
                    . '&transactionreference=' . $reference;
            }

            return $response;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Interswitch initializePayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $amount = 0; // amount not always available; pass 0 for re-query
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v3/purchases/requery', [
                'headers' => $this->authHeaders(false),
                'query'   => [
                    'merchantcode'         => $this->config['merchant_code'],
                    'transactionreference' => $reference,
                    'amount'               => $amount,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Interswitch verifyPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        try {
            $payload = ['transactionReference' => $reference];
            if ($amount !== null) {
                $payload['amount'] = (string) $this->convertAmount($amount);
            }
            return $this->makeRequest('POST', $this->config['base_url'] . '/api/v3/purchases/refund', [
                'headers' => $this->authHeaders(true),
                'json'    => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Interswitch refundPayment failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTransactions(array $params = []): array
    {
        try {
            return $this->makeRequest('GET', $this->config['base_url'] . '/api/v3/purchases', [
                'headers' => $this->authHeaders(false),
                'query'   => array_merge(['merchantCode' => $this->config['merchant_code']], $params),
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Interswitch getTransactions failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authenticate(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $response = $this->makeRequest('POST', $this->config['passport_url'] . '/passport/oauth/token', [
            'auth'        => [$this->config['client_id'], $this->config['client_secret']],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $this->accessToken    = $response['access_token']
            ?? throw new \RuntimeException('Interswitch authentication failed: no access_token.');
        $this->tokenExpiresAt = time() + (int) ($response['expires_in'] ?? 3600) - 60;

        return $this->accessToken;
    }

    private function authHeaders(bool $withContentType = true): array
    {
        $token   = $this->authenticate();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'terminalid'    => $this->config['terminal_id'] ?? '',
        ];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }
}
