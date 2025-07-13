<?php

namespace Kudos\PaymentMadeEasy\Drivers;

class PaystackDriver extends AbstractPaymentDriver
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
}