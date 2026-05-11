<?php

namespace NexusPay\PaymentMadeEasy;

use NexusPay\PaymentMadeEasy\Drivers\BudpayDriver;
use NexusPay\PaymentMadeEasy\Drivers\FlutterwaveDriver;
use NexusPay\PaymentMadeEasy\Drivers\InterswitchDriver;
use NexusPay\PaymentMadeEasy\Drivers\MonnifyDriver;
use NexusPay\PaymentMadeEasy\Drivers\MPesaDriver;
use NexusPay\PaymentMadeEasy\Drivers\MTNMoMoDriver;
use NexusPay\PaymentMadeEasy\Drivers\PaddleDriver;
use NexusPay\PaymentMadeEasy\Drivers\PayPalDriver;
use NexusPay\PaymentMadeEasy\Drivers\PaystackDriver;
use NexusPay\PaymentMadeEasy\Drivers\RazorpayDriver;
use NexusPay\PaymentMadeEasy\Drivers\RemitaDriver;
use NexusPay\PaymentMadeEasy\Drivers\SeerbitDriver;
use NexusPay\PaymentMadeEasy\Drivers\SquadDriver;
use NexusPay\PaymentMadeEasy\Drivers\StripeDriver;
use NexusPay\PaymentMadeEasy\Webhooks\BudpayWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\FlutterwaveWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\InterswitchWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\MonnifyWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\MPesaWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\MTNMoMoWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\PaddleWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\PayPalWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\PaystackWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\RazorpayWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\RemitaWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\SeerbitWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\SquadWebhookHandler;
use NexusPay\PaymentMadeEasy\Webhooks\StripeWebhookHandler;

/**
 * Single source of truth for supported gateway slugs and their driver / webhook classes.
 */
final class GatewayRegistry
{
    /**
     * @var array<string, class-string>
     */
    public const DRIVER_CLASSES = [
        'paystack'    => PaystackDriver::class,
        'flutterwave' => FlutterwaveDriver::class,
        'stripe'      => StripeDriver::class,
        'seerbit'     => SeerbitDriver::class,
        'monnify'     => MonnifyDriver::class,
        'squad'       => SquadDriver::class,
        'remita'      => RemitaDriver::class,
        'budpay'      => BudpayDriver::class,
        'interswitch' => InterswitchDriver::class,
        'paypal'      => PayPalDriver::class,
        'mpesa'       => MPesaDriver::class,
        'mtnmomo'     => MTNMoMoDriver::class,
        'razorpay'    => RazorpayDriver::class,
        'paddle'      => PaddleDriver::class,
    ];

    /**
     * @var array<string, class-string>
     */
    public const WEBHOOK_HANDLER_CLASSES = [
        'paystack'    => PaystackWebhookHandler::class,
        'flutterwave' => FlutterwaveWebhookHandler::class,
        'stripe'      => StripeWebhookHandler::class,
        'seerbit'     => SeerbitWebhookHandler::class,
        'monnify'     => MonnifyWebhookHandler::class,
        'squad'       => SquadWebhookHandler::class,
        'remita'      => RemitaWebhookHandler::class,
        'budpay'      => BudpayWebhookHandler::class,
        'interswitch' => InterswitchWebhookHandler::class,
        'paypal'      => PayPalWebhookHandler::class,
        'mpesa'       => MPesaWebhookHandler::class,
        'mtnmomo'     => MTNMoMoWebhookHandler::class,
        'razorpay'    => RazorpayWebhookHandler::class,
        'paddle'      => PaddleWebhookHandler::class,
    ];

    public static function driverClass(string $gateway): ?string
    {
        return self::DRIVER_CLASSES[$gateway] ?? null;
    }

    public static function webhookHandlerClass(string $gateway): ?string
    {
        return self::WEBHOOK_HANDLER_CLASSES[$gateway] ?? null;
    }

    public static function webhookRoutePattern(): string
    {
        return implode('|', array_keys(self::WEBHOOK_HANDLER_CLASSES));
    }
}
