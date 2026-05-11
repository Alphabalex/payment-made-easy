<?php

namespace NexusPay\PaymentMadeEasy;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use NexusPay\PaymentMadeEasy\Console\Commands\PaymentGatewaysCommand;
use NexusPay\PaymentMadeEasy\Console\Commands\PaymentTransactionsCommand;
use NexusPay\PaymentMadeEasy\Console\Commands\PaymentVerifyCommand;
use NexusPay\PaymentMadeEasy\Console\Commands\PaymentWebhookReplayCommand;
use NexusPay\PaymentMadeEasy\Events\ChargebackCreated;
use NexusPay\PaymentMadeEasy\Events\DisputeCreated;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\PaymentPending;
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCancelled;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCreated;
use NexusPay\PaymentMadeEasy\Events\SubscriptionRenewed;
use NexusPay\PaymentMadeEasy\Events\TransferFailed;
use NexusPay\PaymentMadeEasy\Events\TransferSuccessful;
use NexusPay\PaymentMadeEasy\Contracts\WebhookLogSanitizerInterface;
use NexusPay\PaymentMadeEasy\Listeners\PersistWebhookPaymentEvents;
use NexusPay\PaymentMadeEasy\Services\PaymentRecorder;
use NexusPay\PaymentMadeEasy\Support\DefaultWebhookLogSanitizer;

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

        $this->app->singleton(DefaultWebhookLogSanitizer::class);

        $this->app->bind(WebhookLogSanitizerInterface::class, function ($app) {
            $class = config('payment-gateways.webhooks.log_sanitizer');
            if (is_string($class) && $class !== '' && class_exists($class)) {
                $resolved = $app->make($class);
                if (!$resolved instanceof WebhookLogSanitizerInterface) {
                    throw new \InvalidArgumentException(
                        "payment-gateways.webhooks.log_sanitizer must resolve to an implementation of " . WebhookLogSanitizerInterface::class
                    );
                }

                return $resolved;
            }

            return $app->make(DefaultWebhookLogSanitizer::class);
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

        $this->registerWebhookRecordingListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PaymentGatewaysCommand::class,
                PaymentTransactionsCommand::class,
                PaymentVerifyCommand::class,
                PaymentWebhookReplayCommand::class,
            ]);
        }
    }

    protected function registerWebhookRecordingListeners(): void
    {
        Event::listen(PaymentSuccessful::class, [PersistWebhookPaymentEvents::class, 'handlePaymentSuccessful']);
        Event::listen(PaymentFailed::class, [PersistWebhookPaymentEvents::class, 'handlePaymentFailed']);
        Event::listen(PaymentPending::class, [PersistWebhookPaymentEvents::class, 'handlePaymentPending']);
        Event::listen(RefundProcessed::class, [PersistWebhookPaymentEvents::class, 'handleRefundProcessed']);
        Event::listen(DisputeCreated::class, [PersistWebhookPaymentEvents::class, 'handleDisputeCreated']);
        Event::listen(ChargebackCreated::class, [PersistWebhookPaymentEvents::class, 'handleChargebackCreated']);
        Event::listen(TransferSuccessful::class, [PersistWebhookPaymentEvents::class, 'handleTransferSuccessful']);
        Event::listen(TransferFailed::class, [PersistWebhookPaymentEvents::class, 'handleTransferFailed']);
        Event::listen(SubscriptionCreated::class, [PersistWebhookPaymentEvents::class, 'handleSubscriptionCreated']);
        Event::listen(SubscriptionRenewed::class, [PersistWebhookPaymentEvents::class, 'handleSubscriptionRenewed']);
        Event::listen(SubscriptionCancelled::class, [PersistWebhookPaymentEvents::class, 'handleSubscriptionCancelled']);
    }
}
