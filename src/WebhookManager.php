<?php

namespace NexusPay\PaymentMadeEasy;

use Illuminate\Http\Request;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;
use NexusPay\PaymentMadeEasy\Webhooks\BudpayWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\FlutterwaveWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\InterswitchWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\MonnifyWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\PaystackWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\RemitaWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\SeerbitWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\SquadWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\StripeWebhookHandler;
use NexusPay\PaymentMadeEasy\Contracts\WebhookHandlerInterface;

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
            'paystack'    => new PaystackWebhookHandler($config, $gateway),
            'flutterwave' => new FlutterwaveWebhookHandler($config, $gateway),
            'stripe'      => new StripeWebhookHandler($config, $gateway),
            'seerbit'     => new SeerbitWebhookHandler($config, $gateway),
            'monnify'     => new MonnifyWebhookHandler($config, $gateway),
            'squad'       => new SquadWebhookHandler($config, $gateway),
            'remita'      => new RemitaWebhookHandler($config, $gateway),
            'budpay'      => new BudpayWebhookHandler($config, $gateway),
            'interswitch' => new InterswitchWebhookHandler($config, $gateway),
            default       => throw new WebhookException("Unsupported gateway: {$gateway}"),
        };
    }
}
