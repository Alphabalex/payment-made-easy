<?php

namespace NexusPay\PaymentMadeEasy\Contracts;

interface WebhookLogSanitizerInterface
{
    /**
     * Return a copy of the payload safe for structured logs (redact secrets / PII keys).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array;
}
