<?php

// =============================================================================
// WEBHOOK EVENT REGISTRATION
// In app/Providers/EventServiceProvider.php
// =============================================================================

use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\PaymentFailed;
use NexusPay\PaymentMadeEasy\Events\PaymentPending;
use NexusPay\PaymentMadeEasy\Events\RefundProcessed;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCreated;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCancelled;
use NexusPay\PaymentMadeEasy\Events\SubscriptionRenewed;
use NexusPay\PaymentMadeEasy\Events\TransferSuccessful;
use NexusPay\PaymentMadeEasy\Events\TransferFailed;
use NexusPay\PaymentMadeEasy\Events\DisputeCreated;
use NexusPay\PaymentMadeEasy\Events\ChargebackCreated;

protected $listen = [
    // One-time payments
    PaymentSuccessful::class   => [App\Listeners\HandleSuccessfulPayment::class],
    PaymentFailed::class       => [App\Listeners\HandleFailedPayment::class],
    PaymentPending::class      => [App\Listeners\HandlePendingPayment::class],
    RefundProcessed::class     => [App\Listeners\HandleRefundProcessed::class],

    // Subscriptions
    SubscriptionCreated::class   => [App\Listeners\HandleSubscriptionCreated::class],
    SubscriptionCancelled::class => [App\Listeners\HandleSubscriptionCancelled::class],
    SubscriptionRenewed::class   => [App\Listeners\HandleSubscriptionRenewed::class],

    // Disbursements
    TransferSuccessful::class => [App\Listeners\HandleTransferSuccessful::class],
    TransferFailed::class     => [App\Listeners\HandleTransferFailed::class],

    // Disputes / chargebacks
    DisputeCreated::class    => [App\Listeners\HandleDisputeCreated::class],
    ChargebackCreated::class => [App\Listeners\HandleChargebackCreated::class],
];

// =============================================================================
// EXAMPLE LISTENERS
// =============================================================================

// --- One-time payment success ---
class HandleSuccessfulPayment
{
    public function handle(PaymentSuccessful $event): void
    {
        $gateway   = $event->webhookEvent->getGateway();   // 'paystack'
        $eventType = $event->webhookEvent->getEventType(); // 'charge.success'
        $data      = $event->paymentData;

        // $data keys (normalized across gateways):
        // reference, amount, currency, status, customer_email, transaction_date, metadata

        Order::where('reference', $data['reference'])->update(['status' => 'paid']);
    }
}

// --- Subscription created ---
class HandleSubscriptionCreated
{
    public function handle(SubscriptionCreated $event): void
    {
        $data = $event->subscriptionData;

        // $data keys: subscription_code, plan_code, customer_email, status, amount, currency, next_payment_date

        UserSubscription::create([
            'user_email'         => $data['customer_email'],
            'plan_code'          => $data['plan_code'],
            'subscription_code'  => $data['subscription_code'],
            'status'             => 'active',
            'next_renewal'       => $data['next_payment_date'] ?? null,
        ]);
    }
}

// --- Subscription cancelled ---
class HandleSubscriptionCancelled
{
    public function handle(SubscriptionCancelled $event): void
    {
        $data   = $event->subscriptionData;
        $reason = $event->reason;   // cancellation reason if provided

        UserSubscription::where('subscription_code', $data['subscription_code'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}

// --- Subscription renewed / invoice paid ---
class HandleSubscriptionRenewed
{
    public function handle(SubscriptionRenewed $event): void
    {
        $data        = $event->subscriptionData;
        $invoiceData = $event->invoiceData;

        UserSubscription::where('subscription_code', $data['subscription_code'])
            ->update(['status' => 'active', 'next_renewal' => $data['next_payment_date'] ?? null]);
    }
}

// --- Transfer / payout success ---
class HandleTransferSuccessful
{
    public function handle(TransferSuccessful $event): void
    {
        $data = $event->transferData;

        // $data keys: transfer_code, reference, amount, currency, status, recipient_name, recipient_account, bank_name, reason

        Payout::where('reference', $data['reference'])->update(['status' => 'completed']);
    }
}

// --- Transfer / payout failed ---
class HandleTransferFailed
{
    public function handle(TransferFailed $event): void
    {
        $data   = $event->transferData;
        $reason = $event->reason;

        Payout::where('reference', $data['reference'])
            ->update(['status' => 'failed', 'failure_reason' => $reason]);
    }
}

// --- Dispute raised ---
class HandleDisputeCreated
{
    public function handle(DisputeCreated $event): void
    {
        $data = $event->disputeData;

        // Notify finance team, flag transaction for review
        Notification::route('mail', 'finance@yoursite.com')
            ->notify(new DisputeOpenedNotification($data));
    }
}

// --- Chargeback raised ---
class HandleChargebackCreated
{
    public function handle(ChargebackCreated $event): void
    {
        $data = $event->chargebackData;

        // Escalate to finance
    }
}

// =============================================================================
// MANUAL / CUSTOM WEBHOOK HANDLING
// =============================================================================

use NexusPay\PaymentMadeEasy\WebhookManager;
use NexusPay\PaymentMadeEasy\Exceptions\WebhookException;

class CustomWebhookController extends Controller
{
    public function handle(Request $request, string $gateway, WebhookManager $webhookManager)
    {
        try {
            $webhookManager->handle($gateway, $request);
            return response()->json(['status' => 'success']);
        } catch (WebhookException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

// =============================================================================
// WEBHOOK ENV SETTINGS (add to .env)
// =============================================================================
/*
PAYMENT_WEBHOOKS_ENABLED=true
PAYMENT_WEBHOOK_VERIFY_SIGNATURE=true
PAYMENT_WEBHOOK_LOG_EVENTS=true
PAYMENT_WEBHOOK_QUEUE_EVENTS=false

PAYSTACK_WEBHOOK_SECRET=your_paystack_webhook_secret
FLUTTERWAVE_WEBHOOK_SECRET=your_flutterwave_webhook_secret
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
SEERBIT_WEBHOOK_SECRET=your_seerbit_webhook_secret
*/