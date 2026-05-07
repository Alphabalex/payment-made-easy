<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

/**
 * M-Pesa payment driver (Safaricom Kenya).
 *
 * Supports STK Push (Lipa Na M-Pesa Online / C2B) and B2C payouts.
 *
 * Base URL: https://api.safaricom.co.ke (live) / https://sandbox.safaricom.co.ke (sandbox)
 * Authentication: OAuth2 client_credentials
 *   GET /oauth/v1/generate?grant_type=client_credentials  (Basic Auth: consumer_key:consumer_secret)
 *
 * Amount convention: KES whole numbers (integer).
 * All amounts passed in must be in major currency units (KES).
 */
class MPesaDriver extends AbstractPaymentDriver
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

        $response = $this->makeRequest('GET', $this->config['base_url'] . '/oauth/v1/generate?grant_type=client_credentials', [
            'auth' => [$this->config['consumer_key'], $this->config['consumer_secret']],
        ]);

        $this->accessToken    = $response['access_token'];
        $this->tokenExpiresAt = time() + (int) ($response['expires_in'] ?? 3600) - 60;

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

    /** Build the base64-encoded password for STK Push: base64(shortcode + passkey + timestamp) */
    private function buildPassword(string $timestamp): string
    {
        return base64_encode($this->config['shortcode'] . $this->config['passkey'] . $timestamp);
    }

    // -------------------------------------------------------------------------
    // PaymentDriverInterface
    // -------------------------------------------------------------------------

    /**
     * Initiate an STK Push (prompt on customer's phone).
     *
     * Required fields:
     *   phone  — customer's phone number in international format: 254XXXXXXXXX
     *   amount — KES amount (whole number)
     *
     * Optional:
     *   account_reference — shown on the customer's phone (default: reference)
     *   transaction_desc  — short description
     *   callback_url      — override config callback
     */
    public function initializePayment(array $data): array
    {
        $timestamp = date('YmdHis');
        $reference = $data['reference'] ?? $this->generateReference('mpesa');
        $phone     = ltrim($data['phone'] ?? '', '+');   // ensure 254XXXXXXXXX
        $amount    = (int) $data['amount'];

        $payload = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password'          => $this->buildPassword($timestamp),
            'Timestamp'         => $timestamp,
            'TransactionType'   => $data['transaction_type'] ?? 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $this->config['shortcode'],
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $data['callback_url'] ?? $this->config['callback_url'],
            'AccountReference'  => $data['account_reference'] ?? $reference,
            'TransactionDesc'   => $data['transaction_desc'] ?? $data['description'] ?? 'Payment',
        ];

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/mpesa/stkpush/v1/processrequest', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);

        // Attach the reference so callers can store it
        $response['data']['reference']       = $reference;
        $response['data']['checkout_request_id'] = $response['CheckoutRequestID'] ?? '';

        return $response;
    }

    /**
     * Query the status of an STK Push.
     * $reference is the CheckoutRequestID returned by initializePayment.
     */
    public function verifyPayment(string $reference): array
    {
        $timestamp = date('YmdHis');

        return $this->makeRequest('POST', $this->config['base_url'] . '/mpesa/stkpushquery/v1/query', [
            'headers' => $this->authHeaders(),
            'json'    => [
                'BusinessShortCode' => $this->config['shortcode'],
                'Password'          => $this->buildPassword($timestamp),
                'Timestamp'         => $timestamp,
                'CheckoutRequestID' => $reference,
            ],
        ]);
    }

    public function getPayment(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    /**
     * M-Pesa does not have a standard refund endpoint.
     * Reverse a C2B transaction via B2B reversal API.
     */
    public function refundPayment(string $reference, ?float $amount = null): array
    {
        $payload = [
            'Initiator'              => $this->config['initiator_name'],
            'SecurityCredential'     => $this->config['security_credential'],
            'CommandID'              => 'TransactionReversal',
            'TransactionID'          => $reference,
            'Amount'                 => (int) ($amount ?? 0),
            'ReceiverParty'          => $this->config['shortcode'],
            'ReceiverIdentifierType' => '11',
            'QueueTimeOutURL'        => $this->config['timeout_url'] ?? $this->config['callback_url'],
            'ResultURL'              => $this->config['result_url'] ?? $this->config['callback_url'],
            'Remarks'                => 'Reversal',
            'Occasion'               => '',
        ];

        return $this->makeRequest('POST', $this->config['base_url'] . '/mpesa/reversal/v1/request', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }

    /** Query a C2B transaction status. */
    public function getTransactions(array $filters = []): array
    {
        $payload = [
            'Initiator'          => $this->config['initiator_name'],
            'SecurityCredential' => $this->config['security_credential'],
            'CommandID'          => 'TransactionStatusQuery',
            'TransactionID'      => $filters['transaction_id'] ?? '',
            'PartyA'             => $this->config['shortcode'],
            'IdentifierType'     => '4',
            'ResultURL'          => $this->config['result_url'] ?? $this->config['callback_url'],
            'QueueTimeOutURL'    => $this->config['timeout_url'] ?? $this->config['callback_url'],
            'Remarks'            => 'Transaction status query',
            'Occasion'           => '',
        ];

        return $this->makeRequest('POST', $this->config['base_url'] . '/mpesa/transactionstatus/v1/query', [
            'headers' => $this->authHeaders(),
            'json'    => $payload,
        ]);
    }
}
