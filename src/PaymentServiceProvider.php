<?php

namespace NexusPay\PaymentMadeEasy;

use Illuminate\Support\ServiceProvider;
use NexusPay\PaymentMadeEasy\Console\Commands\PaymentGatewaysCommand;
use NexusPay\PaymentMadeEasy\Console\Commands\PaymentVerifyCommand;
use NexusPay\PaymentMadeEasy\Console\Commands\PaymentWebhookReplayCommand;
use NexusPay\PaymentMadeEasy\Services\PaymentRecorder;

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

        $this->app->singleton(PaymentRecorder::class, function () {
            return new PaymentRecorder();
        });

        $this->app->alias(PaymentManager::class, 'payment-gateways');
        $this->app->alias(WebhookManager::class, 'payment-webhooks');
        $this->app->alias(PaymentRecorder::class, 'payment-recorder');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payment-gateways.php' => config_path('payment-gateways.php'),
            ], 'payment-gateways-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'payment-gateways-migrations');
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/webhooks.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PaymentGatewaysCommand::class,
                PaymentVerifyCommand::class,
                PaymentWebhookReplayCommand::class,
            ]);
        }
    }
}
