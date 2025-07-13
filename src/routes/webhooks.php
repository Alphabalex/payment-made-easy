<?php

use Illuminate\Support\Facades\Route;
use NexusPay\PaymentMadeEasy\Http\Controllers\WebhookController;

Route::prefix('webhooks/payment-gateways')
    ->name('payment-gateways.webhooks.')
    ->group(function () {
        Route::post('{gateway}', [WebhookController::class, 'handle'])
            ->name('handle')
            ->where('gateway', 'paystack');
    });
