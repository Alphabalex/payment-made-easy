<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

class FlutterwaveDriver extends AbstractPaymentDriver
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
}