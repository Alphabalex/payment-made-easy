# Payment Made Easy

A comprehensive Laravel package for integrating multiple payment gateways with support for one-time payments, subscriptions/plans, disbursements/transfers, virtual accounts, and payment links — all behind a consistent interface.

## Supported Gateways

| Gateway         | One-Time | Subscriptions | Disbursements | Virtual Accounts | Payment Links |
| --------------- | :------: | :-----------: | :-----------: | :--------------: | :-----------: |
| **Paystack**    |    ✅     |       ✅       |       ✅       |        ✅         |       ✅       |
| **Flutterwave** |    ✅     |       ✅       |       ✅       |        ✅         |       ✅       |
| **Stripe**      |    ✅     |       ✅       |       —       |        —         |       ✅       |
| **Seerbit**     |    ✅     |       ✅       |       —       |        ✅         |       ✅       |

> Capability detection is done via `instanceof` — drivers only implement the interfaces they support. No breaking changes when new capabilities are added.

---

## Installation

### 1. Install via Composer

```bash
composer require nexuspay/payment-made-easy
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="NexusPay\PaymentMadeEasy\PaymentServiceProvider"
```

### 3. Environment Variables

```env
# Default gateway & currency
PAYMENT_GATEWAY=paystack
PAYMENT_CURRENCY=NGN

# Paystack
PAYSTACK_PUBLIC_KEY=pk_live_xxxxx
PAYSTACK_SECRET_KEY=sk_live_xxxxx
PAYSTACK_CALLBACK_URL=https://yoursite.com/payment/callback
PAYSTACK_WEBHOOK_SECRET=your_paystack_webhook_secret

# Flutterwave
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK-xxxxx
FLUTTERWAVE_SECRET_KEY=FLWSECK-xxxxx
FLUTTERWAVE_ENCRYPTION_KEY=your_flutterwave_encryption_key
FLUTTERWAVE_CALLBACK_URL=https://yoursite.com/payment/callback
FLUTTERWAVE_WEBHOOK_SECRET=your_flutterwave_webhook_secret

# Stripe
STRIPE_PUBLIC_KEY=pk_live_xxxxx
STRIPE_SECRET_KEY=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
STRIPE_CALLBACK_URL=https://yoursite.com/payment/callback

# Seerbit
SEERBIT_PUBLIC_KEY=your_seerbit_public_key
SEERBIT_SECRET_KEY=your_seerbit_secret_key
SEERBIT_BASE_URL=https://seerbitapi.com/api/v2
SEERBIT_CALLBACK_URL=https://yoursite.com/payment/callback
SEERBIT_WEBHOOK_SECRET=your_seerbit_webhook_secret

# Webhook settings
PAYMENT_WEBHOOKS_ENABLED=true
PAYMENT_WEBHOOK_VERIFY_SIGNATURE=true
PAYMENT_WEBHOOK_LOG_EVENTS=true
PAYMENT_WEBHOOK_QUEUE_EVENTS=false
```

---

## One-Time Payments

```php
use NexusPay\PaymentMadeEasy\Facades\Payment;

// Initialize
$response = Payment::driver('paystack')->initializePayment([
    'email'        => 'customer@example.com',
    'amount'       => 5000.00,   // always in major units (NGN, USD, etc.)
    'reference'    => 'ORDER_123',
    'callback_url' => 'https://yoursite.com/payment/callback',
    'metadata'     => ['order_id' => '123'],
]);

// Redirect customer to $response['data']['authorization_url']

// Verify after callback
$verification = Payment::driver('paystack')->verifyPayment('ORDER_123');

// Refund
$refund = Payment::driver('paystack')->refundPayment('ORDER_123', 2500.00); // partial
$full   = Payment::driver('paystack')->refundPayment('ORDER_123');           // full

// List transactions
$txns = Payment::driver('paystack')->getTransactions(['per_page' => 50, 'page' => 1]);
```

---

## Subscriptions & Plans

> Available on: **Paystack**, **Flutterwave**, **Stripe**, **Seerbit**

```php
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;

$driver = Payment::driver('paystack');

// --- Plans ---
$plan = $driver->createPlan([
    'name'     => 'Pro Monthly',
    'amount'   => 5000.00,
    'interval' => 'monthly',   // monthly | weekly | annually | quarterly
]);
$planCode = $plan['data']['plan_code'];

$driver->updatePlan($planCode, ['name' => 'Pro Monthly (Updated)']);
$driver->getPlan($planCode);
$driver->listPlans(['per_page' => 20]);
$driver->deletePlan($planCode);

// --- Subscriptions ---
$sub = $driver->createSubscription([
    'email'      => 'customer@example.com',
    'plan_code'  => $planCode,
    'start_date' => '2026-06-01T00:00:00.000Z', // optional
]);
$subCode = $sub['data']['subscription_code'];

$driver->getSubscription($subCode);
$driver->listSubscriptions(['plan' => $planCode]);
$driver->listCustomerSubscriptions('customer@example.com');
$driver->pauseSubscription($subCode);
$driver->resumeSubscription($subCode);
$driver->cancelSubscription($subCode);
```

---

## Disbursements & Transfers

> Available on: **Paystack**, **Flutterwave**

```php
// Resolve an account number before transferring
$account = Payment::driver('paystack')->resolveAccountNumber([
    'account_number' => '0123456789',
    'bank_code'      => '044',
]);

// Create recipient
$recipient = Payment::driver('paystack')->createTransferRecipient([
    'name'           => 'Jane Doe',
    'account_number' => '0123456789',
    'bank_code'      => '044',
    'currency'       => 'NGN',
]);
$recipientCode = $recipient['data']['recipient_code'];

// Single transfer
$transfer = Payment::driver('paystack')->transfer([
    'amount'    => 10000.00,
    'recipient' => $recipientCode,
    'reason'    => 'Salary payout',
    'reference' => 'PAYOUT_001',
]);

// Bulk transfer
Payment::driver('paystack')->bulkTransfer([
    'transfers' => [
        ['amount' => 5000.00, 'recipient' => 'RCP_abc', 'reference' => 'B1'],
        ['amount' => 3000.00, 'recipient' => 'RCP_def', 'reference' => 'B2'],
    ],
]);

// Verify & list
Payment::driver('paystack')->verifyTransfer('PAYOUT_001');
Payment::driver('paystack')->listTransfers(['per_page' => 50]);

// List banks
$banks = Payment::driver('paystack')->listBanks(['country' => 'nigeria']);
```

---

## Virtual Accounts

> Available on: **Paystack**, **Flutterwave**, **Seerbit**

```php
// Create a dedicated virtual account (bank transfer → auto-credited)
$va = Payment::driver('paystack')->createVirtualAccount([
    'email'          => 'customer@example.com',
    'name'           => 'Jane Doe',
    'bvn'            => '12345678901',
    'preferred_bank' => 'wema-bank',  // or 'titan-paystack'
]);

$accountNumber = $va['data']['account_number'];
$bankName      = $va['data']['bank']['name'];

Payment::driver('paystack')->getVirtualAccount($va['data']['id']);
Payment::driver('paystack')->listVirtualAccounts(['active' => true]);
Payment::driver('paystack')->deactivateVirtualAccount($va['data']['id']);
```

---

## Payment Links

> Available on: **Paystack**, **Flutterwave**, **Stripe**, **Seerbit**

```php
$link = Payment::driver('paystack')->createPaymentLink([
    'name'        => 'Product Launch Special',
    'amount'      => 2500.00,
    'description' => 'Early-bird ticket',
    'currency'    => 'NGN',
]);

$url = $link['data']['link'];

Payment::driver('paystack')->updatePaymentLink($link['data']['id'], ['amount' => 3000.00]);
Payment::driver('paystack')->getPaymentLink($link['data']['id']);
Payment::driver('paystack')->listPaymentLinks();
Payment::driver('paystack')->disablePaymentLink($link['data']['id']);
```

---

## Webhooks

Webhook routes are registered automatically:

```
POST /webhooks/payment-gateways/{gateway}
```

Configure each gateway's dashboard to point to:

```
https://yoursite.com/webhooks/payment-gateways/paystack
https://yoursite.com/webhooks/payment-gateways/flutterwave
https://yoursite.com/webhooks/payment-gateways/stripe
https://yoursite.com/webhooks/payment-gateways/seerbit
```

### Available Events

| Event Class             | Fired When                           |
| ----------------------- | ------------------------------------ |
| `PaymentSuccessful`     | A payment completes successfully     |
| `PaymentFailed`         | A payment fails                      |
| `PaymentPending`        | A payment is pending                 |
| `RefundProcessed`       | A refund is processed                |
| `SubscriptionCreated`   | A subscription is activated          |
| `SubscriptionCancelled` | A subscription is cancelled          |
| `SubscriptionRenewed`   | A subscription renews / invoice paid |
| `TransferSuccessful`    | A transfer/payout succeeds           |
| `TransferFailed`        | A transfer/payout fails              |
| `DisputeCreated`        | A dispute is raised                  |
| `ChargebackCreated`     | A chargeback is raised               |

### Listening to Events

```php
// app/Providers/EventServiceProvider.php
use NexusPay\PaymentMadeEasy\Events\PaymentSuccessful;
use NexusPay\PaymentMadeEasy\Events\SubscriptionCreated;
use NexusPay\PaymentMadeEasy\Events\TransferSuccessful;

protected $listen = [
    PaymentSuccessful::class   => [App\Listeners\HandleSuccessfulPayment::class],
    SubscriptionCreated::class => [App\Listeners\HandleNewSubscription::class],
    TransferSuccessful::class  => [App\Listeners\HandleTransferSuccessful::class],
];
```

```php
// app/Listeners/HandleSuccessfulPayment.php
class HandleSuccessfulPayment
{
    public function handle(PaymentSuccessful $event): void
    {
        $gateway   = $event->webhookEvent->getGateway();  // 'paystack'
        $reference = $event->paymentData['reference'];
        $amount    = $event->paymentData['amount'];

        // update order, send receipt, etc.
    }
}
```

---

## Capability Detection

Check what a driver supports at runtime:

```php
use NexusPay\PaymentMadeEasy\Contracts\SubscriptionDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\DisbursementDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\VirtualAccountDriverInterface;
use NexusPay\PaymentMadeEasy\Contracts\PaymentLinkDriverInterface;

$driver = Payment::driver('paystack');

if ($driver instanceof SubscriptionDriverInterface) {
    $driver->createPlan([...]);
}

if ($driver instanceof DisbursementDriverInterface) {
    $driver->transfer([...]);
}

if ($driver instanceof VirtualAccountDriverInterface) {
    $driver->createVirtualAccount([...]);
}

if ($driver instanceof PaymentLinkDriverInterface) {
    $driver->createPaymentLink([...]);
}
```

---

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email babusunnah@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
