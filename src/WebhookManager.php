<?php

namespace NexusPay\PaymentMadeEasy;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Contracts\WebhookHandlerInterface;
use NexusPay\PaymentMadeEasy\Webhooks\PaystackWebhookHandler;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class WebhookManager
{
    protected array $handlers = [];

    public function __construct()
    {
        $this->registerHandlers();
    }

    public function handle(string $gateway, Request $request): void
    {
        $handler = $this->getHandler($gateway);

        if (!$handler->verifySignature($request)) {
            throw new WebhookException('Invalid webhook signature');
        }

        $event = $handler->parsePayload($request);
        $handler->handle($event);
    }

    public function getHandler(string $gateway): WebhookHandlerInterface
    {
        if (!isset($this->handlers[$gateway])) {
            throw new WebhookException("No webhook handler found for gateway: {$gateway}");
        }

        return $this->handlers[$gateway];
    }

    protected function registerHandlers(): void
    {
        $gateways = config('payment-gateways.gateways', []);

        foreach ($gateways as $name => $config) {
            $this->handlers[$name] = $this->createHandler($name, $config);
        }
    }

    protected function createHandler(string $gateway, array $config): WebhookHandlerInterface
    {
        return match ($gateway) {
            'paystack' => new PaystackWebhookHandler($config, $gateway),
            default => throw new WebhookException("Unsupported gateway: {$gateway}"),
        };
    }
}
