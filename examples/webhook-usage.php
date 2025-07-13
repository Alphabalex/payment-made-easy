<?php

// In your EventServiceProvider.php
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;

protected $listen = [
    PaymentSuccessful::class => [
        'App\Listeners\HandleSuccessfulPayment',
    ],
    PaymentFailed::class => [
        'App\Listeners\HandleFailedPayment',
    ],
    RefundProcessed::class => [
        'App\Listeners\HandleRefundProcessed',
    ],
];

// Example Listener
class HandleSuccessfulPayment
{
    public function handle(PaymentSuccessful $event)
    {
        $paymentData = $event->paymentData;
        $gateway = $event->webhookEvent->getGateway();
        
        // Update your database
        // Send confirmation emails
        // Update order status
        // etc.
        
        Log::info('Payment successful', [
            'gateway' => $gateway,
            'reference' => $paymentData['reference'],
            'amount' => $paymentData['amount'],
        ]);
    }
}

// Manual webhook handling
use YourVendor\LaravelPaymentGateways\WebhookManager;

class CustomWebhookController extends Controller
{
    public function handleCustomWebhook(Request $request, WebhookManager $webhookManager)
    {
        try {
            $webhookManager->handle('paystack', $request);
            return response()->json(['status' => 'success']);
        } catch (WebhookException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

// Environment variables for webhooks
/*
PAYMENT_WEBHOOKS_ENABLED=true
PAYMENT_WEBHOOK_VERIFY_SIGNATURE=true
PAYMENT_WEBHOOK_LOG_EVENTS=true
PAYMENT_WEBHOOK_QUEUE_EVENTS=false

PAYSTACK_WEBHOOK_SECRET=your_paystack_webhook_secret
*/