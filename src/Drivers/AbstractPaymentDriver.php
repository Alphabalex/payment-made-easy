<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use NexusPay\PaymentMadeEasy\Contracts\PaymentDriverInterface;
use NexusPay\PaymentMadeEasy\Exceptions\PaymentException;

abstract class AbstractPaymentDriver implements PaymentDriverInterface
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Make HTTP request using Laravel's HTTP client (Guzzle under the hood).
     *
     * @param  string  $method  GET, POST, PUT, PATCH, DELETE
     * @param  string  $url
     * @param  array<string, mixed>  $options  Guzzle-style: headers, json, form_params, query, auth (basic [user, pass])
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        $timeout = (float) ($this->config['http_timeout'] ?? 30);
        $verify = $this->config['http_verify'] ?? true;

        $pending = Http::timeout($timeout)->withOptions(['verify' => $verify]);

        if (!empty($options['auth']) && is_array($options['auth']) && count($options['auth']) === 2) {
            $pending = $pending->withBasicAuth((string) $options['auth'][0], (string) $options['auth'][1]);
        }

        if (!empty($options['headers'])) {
            $pending = $pending->withHeaders($options['headers']);
        }

        try {
            $response = $this->sendHttpRequest($pending, strtoupper($method), $url, $options);
            $response->throw();

            $statusCode = $response->status();
            $body = $response->body();
            $trimmed = trim($body);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new PaymentException(
                    'Invalid JSON response from payment gateway (HTTP ' . $statusCode . '): ' . json_last_error_msg()
                    . ' — body: ' . mb_substr($trimmed, 0, 500),
                    $statusCode
                );
            }

            return $decoded ?? [];
        } catch (RequestException $e) {
            $res = $e->response;
            $body = $res ? $res->body() : $e->getMessage();
            $code = $res ? $res->status() : ($e->getCode() !== 0 ? $e->getCode() : 0);

            throw new PaymentException('Payment request failed: ' . $body, $code, $e);
        } catch (ConnectionException $e) {
            throw new PaymentException('Payment request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function sendHttpRequest(PendingRequest $pending, string $method, string $url, array $options)
    {
        $query = $options['query'] ?? [];

        $payload = null;
        if (isset($options['json'])) {
            $pending = $pending->asJson();
            $payload = $options['json'];
        } elseif (isset($options['form_params'])) {
            $pending = $pending->asForm();
            $payload = $options['form_params'];
        }

        return match ($method) {
            'GET' => $pending->get($url, $query),
            'POST' => $payload !== null
                ? $pending->post($url, $payload)
                : $pending->post($url),
            'PUT' => $payload !== null
                ? $pending->put($url, $payload)
                : $pending->put($url),
            'PATCH' => $payload !== null
                ? $pending->patch($url, $payload)
                : $pending->patch($url),
            'DELETE' => $payload !== null
                ? $pending->delete($url, $payload)
                : $pending->delete($url),
            default => throw new PaymentException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Generate a unique reference
     *
     * @param  string  $prefix
     * @return string
     */
    protected function generateReference(string $prefix = 'ref'): string
    {
        return $prefix . '_' . str_replace('-', '', (string) Str::uuid());
    }

    /**
     * Convert amount to kobo/cents if needed
     *
     * @param  float  $amount
     * @return int
     */
    protected function convertAmount(float $amount): int
    {
        if (extension_loaded('bcmath')) {
            return (int) bcmul((string) $amount, '100', 0);
        }

        return (int) round($amount * 100);
    }
}
