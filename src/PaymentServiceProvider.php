<?php

namespace Kudos\PaymentMadeEasy;

use Illuminate\Support\ServiceProvider;
use Kudos\PaymentMadeEasy\Drivers\PaystackDriver;

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
    }
}