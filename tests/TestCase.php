<?php

namespace NexusPay\PaymentMadeEasy\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use NexusPay\PaymentMadeEasy\PaymentServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PaymentServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Payment' => \NexusPay\PaymentMadeEasy\Facades\Payment::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Default gateway
        $app['config']->set('payment-gateways.default', 'paystack');

        // Paystack test config
        $app['config']->set('payment-gateways.gateways.paystack', [
            'driver'      => 'paystack',
            'public_key'  => 'pk_test_xxx',
            'secret_key'  => 'sk_test_xxx',
            'base_url'    => 'https://api.paystack.co',
            'callback_url' => 'https://example.com/callback',
        ]);

        // Stripe test config
        $app['config']->set('payment-gateways.gateways.stripe', [
            'driver'      => 'stripe',
            'secret_key'  => 'sk_test_xxx',
            'public_key'  => 'pk_test_xxx',
            'currency'    => 'usd',
            'base_url'    => 'https://api.stripe.com',
            'callback_url' => 'https://example.com/callback',
            'webhook_secret' => 'whsec_test_xxx',
        ]);

        // Razorpay test config
        $app['config']->set('payment-gateways.gateways.razorpay', [
            'driver'         => 'razorpay',
            'key_id'         => 'rzp_test_xxx',
            'key_secret'     => 'test_secret',
            'currency'       => 'INR',
            'base_url'       => 'https://api.razorpay.com/v1',
            'callback_url'   => 'https://example.com/callback',
            'webhook_secret' => 'razorpay_webhook_secret',
        ]);

        // Webhooks config
        $app['config']->set('payment-gateways.webhooks.enabled', true);
        $app['config']->set('payment-gateways.webhooks.verify_signature', true);
        $app['config']->set('payment-gateways.webhooks.log_events', false);
        $app['config']->set('payment-gateways.webhooks.queue_events', false);

        // Recording disabled in tests by default
        $app['config']->set('payment-gateways.recording.enabled', false);
    }
}
