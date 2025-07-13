<?php

namespace Kudos\PaymentMadeEasy\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Kudos\PaymentMadeEasy\Contracts\PaymentDriverInterface;
use Kudos\PaymentMadeEasy\Exceptions\PaymentException;

abstract class AbstractPaymentDriver implements PaymentDriverInterface
{
    protected $config;
    protected $client;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Make HTTP request
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return array
     * @throws PaymentException
     */
    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody()->getContents();
            
            return json_decode($body, true) ?? [];
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $body = $response ? $response->getBody()->getContents() : $e->getMessage();
            
            throw new PaymentException("Payment request failed: " . $body, $e->getCode(), $e);
        }
    }

    /**
     * Generate a unique reference
     *
     * @param string $prefix
     * @return string
     */
    protected function generateReference(string $prefix = 'ref'): string
    {
        return $prefix . '_' . time() . '_' . uniqid();
    }

    /**
     * Convert amount to kobo/cents if needed
     *
     * @param float $amount
     * @return int
     */
    protected function convertAmount(float $amount): int
    {
        return (int) ($amount * 100);
    }
}