<?php

namespace NexusPay\PaymentMadeEasy\Drivers;

class SeerbitDriver extends AbstractPaymentDriver
{
    public function initializePayment(array $data): array
    {
        $payload = [
            'publicKey' => $this->config['public_key'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'country' => $data['country'] ?? 'NG',
            'paymentReference' => $data['reference'] ?? $this->generateReference('seerbit'),
            'email' => $data['email'],
            'productId' => $data['product_id'] ?? 'default_product',
            'productDescription' => $data['description'] ?? 'Payment for services',
            'clientAppCode' => $data['client_app_code'] ?? 'app001',
            'channelType' => $data['channel_type'] ?? 'WEB',
            'redirectUrl' => $data['callback_url'] ?? $this->config['callback_url'],
            'customization' => [
                'theme' => [
                    'border_color' => $data['border_color'] ?? '#000000',
                    'background_color' => $data['background_color'] ?? '#004C64',
                    'button_color' => $data['button_color'] ?? '#0084A0',
                ],
                'payment_method' => $data['payment_methods'] ?? ['card', 'account', 'transfer', 'wallet', 'ussd'],
                'confetti' => $data['confetti'] ?? false,
                'logo' => $data['logo'] ?? '',
            ],
        ];

        // Add customer information if provided
        if (isset($data['customer'])) {
            $payload['mobileNumber'] = $data['customer']['phone'] ?? '';
            $payload['firstName'] = $data['customer']['first_name'] ?? '';
            $payload['lastName'] = $data['customer']['last_name'] ?? '';
        }

        // Add billing address if provided
        if (isset($data['billing_address'])) {
            $payload['billingAddress'] = [
                'country' => $data['billing_address']['country'] ?? 'NG',
                'state' => $data['billing_address']['state'] ?? '',
                'city' => $data['billing_address']['city'] ?? '',
                'address' => $data['billing_address']['address'] ?? '',
                'zipCode' => $data['billing_address']['zip_code'] ?? '',
            ];
        }

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
        $response = $this->makeRequest('GET', $this->config['base_url'] . '/payments/query/' . $reference, [
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
        // First get the original transaction details
        $originalTransaction = $this->verifyPayment($reference);

        if (!$originalTransaction['status'] || $originalTransaction['data']['code'] !== '00') {
            throw new \Exception('Cannot refund: Original transaction not found or not successful');
        }

        $transactionData = $originalTransaction['data']['payments'];
        $refundAmount = $amount ?? $transactionData['amount'];

        $payload = [
            'publicKey' => $this->config['public_key'],
            'refundType' => 'FULL', // or 'PARTIAL'
            'refundAmount' => $refundAmount,
            'paymentReference' => $reference,
            'refundReference' => $this->generateReference('seerbit_refund'),
            'currency' => $transactionData['currency'] ?? 'NGN',
            'refundReason' => 'Customer requested refund',
        ];

        if ($amount && $amount < $transactionData['amount']) {
            $payload['refundType'] = 'PARTIAL';
        }

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/refunds', [
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
        $defaultParams = [
            'page' => 1,
            'perPage' => 50,
        ];

        $queryParams = array_merge($defaultParams, $params);
        $queryString = http_build_query($queryParams);

        $url = $this->config['base_url'] . '/payments?' . $queryString;

        $response = $this->makeRequest('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
            ],
        ]);

        return $response;
    }

    /**
     * Get transaction by date range
     */
    public function getTransactionsByDateRange(string $startDate, string $endDate, array $params = []): array
    {
        $queryParams = array_merge($params, [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        return $this->getTransactions($queryParams);
    }

    /**
     * Get refund status
     */
    public function getRefundStatus(string $refundReference): array
    {
        $response = $this->makeRequest('GET', $this->config['base_url'] . '/refunds/query/' . $refundReference, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
            ],
        ]);

        return $response;
    }

    /**
     * Get supported banks for account payment
     */
    public function getSupportedBanks(string $country = 'NG'): array
    {
        $response = $this->makeRequest('GET', $this->config['base_url'] . '/banks?country=' . $country, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
            ],
        ]);

        return $response;
    }

    /**
     * Validate account number
     */
    public function validateAccountNumber(string $accountNumber, string $bankCode): array
    {
        $payload = [
            'publicKey' => $this->config['public_key'],
            'accountNumber' => $accountNumber,
            'bankCode' => $bankCode,
        ];

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/account/validate', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $response;
    }

    /**
     * Create a payment link
     */
    public function createPaymentLink(array $data): array
    {
        $payload = [
            'publicKey' => $this->config['public_key'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'country' => $data['country'] ?? 'NG',
            'paymentReference' => $data['reference'] ?? $this->generateReference('seerbit_link'),
            'email' => $data['email'],
            'productId' => $data['product_id'] ?? 'default_product',
            'productDescription' => $data['description'] ?? 'Payment for services',
            'linkName' => $data['link_name'] ?? 'Payment Link',
            'linkDescription' => $data['link_description'] ?? 'Payment link for services',
            'redirectUrl' => $data['callback_url'] ?? $this->config['callback_url'],
            'expiryDate' => $data['expiry_date'] ?? null, // Format: YYYY-MM-DD HH:MM:SS
        ];

        $response = $this->makeRequest('POST', $this->config['base_url'] . '/payment-links', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['secret_key'],
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $response;
    }
}
