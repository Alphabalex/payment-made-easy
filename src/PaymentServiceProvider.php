<?php

namespace NexusPay\PaymentMadeEasy;

use Illuminate\Support\ServiceProvider;
use NexusPay\PaymentMadeEasy\Drivers\BudpayDriver;
use NexusPay\PaymentMadeEasy\Drivers\FlutterwaveDriver;
use NexusPay\PaymentMadeEasy\Drivers\InterswitchDriver;
use NexusPay\PaymentMadeEasy\Drivers\MonnifyDriver;
use NexusPay\PaymentMadeEasy\Drivers\PaystackDriver;
use NexusPay\PaymentMadeEasy\Drivers\RemitaDriver;
use NexusPay\PaymentMadeEasy\Drivers\SeerbitDriver;
use NexusPay\PaymentMadeEasy\Drivers\SquadDriver;
use NexusPay\PaymentMadeEasy\Drivers\StripeDriver;

class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payment-gateways.php', 'payment-gateways');

        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager($app);
        });

        $this->app->singleton(WebhookManager::class, function ($app) {
            return new WebhookManager();
        });

        $this->app->alias(PaymentManager::class, 'payment-gateways');
        $this->app->alias(WebhookManager::class, 'payment-webhooks');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payment-gateways.php' => config_path('payment-gateways.php'),
            ], 'payment-gateways-config');
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/webhooks.php');
        $this->registerDrivers();
    }

    protected function registerDrivers()
    {
        $manager = $this->app->make(PaymentManager::class);

        $manager->extend('paystack', function ($app, $config) {
            return new PaystackDriver($config);
        });
        $manager->extend('flutterwave', function ($app, $config) {
            return new FlutterwaveDriver($config);
        });
        $manager->extend('stripe', function ($app, $config) {
            return new StripeDriver($config);
        });
        $manager->extend('seerbit', function ($app, $config) {
            return new SeerbitDriver($config);
        });
        $manager->extend('monnify', function ($app, $config) {
            return new MonnifyDriver($config);
        });
        $manager->extend('squad', function ($app, $config) {
            return new SquadDriver($config);
        });
        $manager->extend('remita', function ($app, $config) {
            return new RemitaDriver($config);
        });
        $manager->extend('budpay', function ($app, $config) {
            return new BudpayDriver($config);
        });
        $manager->extend('interswitch', function ($app, $config) {
            return new InterswitchDriver($config);
        });
    }
}
