<?php

namespace NexusPay\PaymentMadeEasy;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use NexusPay\PaymentMadeEasy\Contracts\WebhookHandlerInterface;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\AbstractWebhookHandler;

class WebhookManager
{
    /**
     * Verify, optionally dedupe, parse and dispatch the webhook.
     *
     * @return bool True if the webhook was processed; false if treated as a duplicate (still safe to return HTTP 2xx).
     */
    public function handle(string $gateway, Request $request): bool
    {
        $handler = $this->getHandler($gateway);

        if ($handler instanceof AbstractWebhookHandler) {
            $handler->ensureSigningSecretConfiguredWhenRequired();
        }

        if (!$handler->verifySignature($request)) {
            throw new WebhookException('Invalid webhook signature');
        }

        $idempotencyKey = null;
        if ($this->idempotencyEnabled()) {
            $idempotencyKey = $this->idempotencyKey($gateway, $request);
            $ttl = (int) config('payment-gateways.webhooks.idempotency.ttl', 86400);
            if (!Cache::add($idempotencyKey, 1, $ttl)) {
                return false;
            }
        }

        try {
            $event = $handler->parsePayload($request);
            $handler->handle($event);
        } catch (\Throwable $e) {
            if ($idempotencyKey !== null) {
                Cache::forget($idempotencyKey);
            }
            throw $e;
        }

        return true;
    }

    /**
     * Build a handler using the current config (tests and runtime-safe; not cached per gateway).
     */
    public function getHandler(string $gateway): WebhookHandlerInterface
    {
        $gateways = config('payment-gateways.gateways', []);
        if (!isset($gateways[$gateway])) {
            throw new WebhookException("No webhook handler found for gateway: {$gateway}");
        }

        $class = GatewayRegistry::webhookHandlerClass($gateway);
        if ($class === null) {
            throw new WebhookException("Unsupported gateway: {$gateway}");
        }

        return new $class($gateways[$gateway], $gateway);
    }

    protected function idempotencyEnabled(): bool
    {
        return (bool) config('payment-gateways.webhooks.idempotency.enabled', false);
    }

    protected function idempotencyKey(string $gateway, Request $request): string
    {
        $prefix = (string) config('payment-gateways.webhooks.idempotency.cache_prefix', 'payment-webhook');
        $raw = $request->getContent();
        if ($raw === '' || $raw === false) {
            $encoded = json_encode($request->request->all(), JSON_UNESCAPED_SLASHES);
            $raw = $encoded !== false ? $encoded : '';
        }

        return $prefix . ':' . $gateway . ':' . hash('sha256', $raw);
    }
}
