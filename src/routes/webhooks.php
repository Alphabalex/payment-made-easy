<?php

use Illuminate\Support\Facades\Route;
use NexusPay\PaymentMadeEasy\GatewayRegistry;
use NexusPay\PaymentMadeEasy\Http\Controllers\WebhookController;

Route::prefix('webhooks/payment-gateways')
    ->middleware(config('payment-gateways.webhooks.middleware', []))
    ->name('payment-gateways.webhooks.')
    ->group(function () {
        Route::post('{gateway}', [WebhookController::class, 'handle'])
            ->name('handle')
            ->where('gateway', GatewayRegistry::webhookRoutePattern());
    });
