<?php

namespace NexusPay\PaymentMadeEasy\Support;

use NexusPay\PaymentMadeEasy\Contracts\WebhookLogSanitizerInterface;

/**
 * Recursively redacts configured keys (case-insensitive) and common secret-like
 * suffixes for webhook / error logging.
 */
class DefaultWebhookLogSanitizer implements WebhookLogSanitizerInterface
{
    /**
     * @return list<string>
     */
    public static function defaultRedactKeys(): array
    {
        return [
            'password',
            'secret',
            'secret_key',
            'api_key',
            'private_key',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'card',
            'card_number',
            'number',
            'pan',
            'cvv',
            'cvc',
            'pin',
            'ssn',
            'routing_number',
            'account_number',
            'bvn',
            'iban',
            'swift',
            'bic',
            'sort_code',
            'bank_account',
            'account_name',
            'msisdn',
            'national_id',
            'nin',
            'date_of_birth',
            'holder_name',
            'security_code',
            'webhook_secret',
            'encrypteddata',
            'encrypted_data',
        ];
    }

    /**
     * Keys whose lowercase name ends with one of these substrings are redacted.
     *
     * @return list<string>
     */
    public static function defaultRedactKeySuffixes(): array
    {
        return [
            '_secret',
            '_api_key',
            '_private_key',
            '_password',
            '_token',
            '_auth',
            '_signature',
        ];
    }

    public function sanitize(array $payload): array
    {
        $extra = config('payment-gateways.webhooks.log_redact_keys', []);
        $keys = self::defaultRedactKeys();
        if (is_array($extra) && $extra !== []) {
            $keys = array_values(array_unique(array_merge($keys, $extra)));
        }
        $lower = array_map('strtolower', $keys);
        $suffixes = array_map('strtolower', self::defaultRedactKeySuffixes());

        return $this->walk($payload, $lower, $suffixes);
    }

    /**
     * @param  list<string>  $lowerKeys
     * @param  list<string>  $lowerSuffixes
     */
    private function walk(mixed $data, array $lowerKeys, array $lowerSuffixes): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->shouldRedactKey($key, $lowerKeys, $lowerSuffixes)) {
                $out[$key] = '[REDACTED]';
                continue;
            }
            $out[$key] = is_array($value) ? $this->walk($value, $lowerKeys, $lowerSuffixes) : $value;
        }

        return $out;
    }

    /**
     * @param  list<string>  $lowerKeys
     * @param  list<string>  $lowerSuffixes
     */
    private function shouldRedactKey(string $key, array $lowerKeys, array $lowerSuffixes): bool
    {
        $k = strtolower($key);
        if (in_array($k, $lowerKeys, true)) {
            return true;
        }
        foreach ($lowerSuffixes as $suffix) {
            if ($suffix !== '' && str_ends_with($k, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
