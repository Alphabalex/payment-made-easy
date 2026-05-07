# Payment Made Easy

A comprehensive Laravel package for integrating multiple payment gateways with support for one-time payments, subscriptions/plans, disbursements/transfers, virtual accounts, and payment links — all behind a consistent interface.

## Supported Gateways

| Gateway         | One-Time | Subscriptions | Disbursements | Virtual Accounts | Payment Links |
| --------------- | :------: | :-----------: | :-----------: | :--------------: | :-----------: |
| **Paystack**    |    ✅     |       ✅       |       ✅       |        ✅         |       ✅       |
| **Flutterwave** |    ✅     |       ✅       |       ✅       |        ✅         |       ✅       |
| **Stripe**      |    ✅     |       ✅       |       —       |        —         |       ✅       |
| **Seerbit**     |    ✅     |       ✅       |       —       |        ✅         |       —       |
| **Monnify**     |    ✅     |     ⚠️ n/a     |       ✅       |        ✅         |       —       |
| **Squad**       |    ✅     |       —       |       ✅       |        ✅         |       —       |
| **Remita**      |    ✅     |       —       |       ✅       |        —         |       —       |
| **Budpay**      |    ✅     |       —       |       ✅       |        ✅         |       —       |
| **Interswitch** |    ✅     |       —       |       —       |        —         |       —       |
| **PayPal**      |    ✅     |       ✅       |       ✅       |        —         |       ✅       |
| **M-Pesa**      |    ✅     |       —       |       —       |        —         |       —       |
| **MTN MoMo**    |    ✅     |       —       |       ✅       |        —         |       —       |
| **Razorpay**    |    ✅     |       ✅       |       ✅       |        —         |       ✅       |
| **Paddle**      |    ✅     |       ✅       |       —       |        —         |       ✅       |

> ⚠️ Monnify implements `SubscriptionDriverInterface` for interface compatibility, but all subscription methods throw `SubscriptionException` — Monnify has no subscription API.

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

### 3. (Optional) Publish & Run Migrations

The package ships with migrations for recording payment activity to your database. This is opt-in:

```bash
# Publish migrations
php artisan vendor:publish --tag=payment-gateways-migrations

# Run migrations
php artisan migrate
```

Then enable recording in your `.env`:

```env
PAYMENT_RECORDING_ENABLED=true
PAYMENT_RECORD_WEBHOOKS=true
```

### 4. Environment Variables

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

# Monnify
MONNIFY_API_KEY=your_monnify_api_key
MONNIFY_SECRET_KEY=your_monnify_secret_key
MONNIFY_CONTRACT_CODE=your_monnify_contract_code
MONNIFY_WALLET_ACCOUNT_NUMBER=your_monnify_wallet_account
MONNIFY_CALLBACK_URL=https://yoursite.com/payment/callback
MONNIFY_WEBHOOK_SECRET=your_monnify_webhook_secret

# Squad (GTCo)
SQUAD_SECRET_KEY=your_squad_secret_key
SQUAD_PUBLIC_KEY=your_squad_public_key
SQUAD_BENEFICIARY_ACCOUNT=your_squad_beneficiary_account
SQUAD_CALLBACK_URL=https://yoursite.com/payment/callback
SQUAD_WEBHOOK_SECRET=your_squad_webhook_secret

# Remita
REMITA_API_KEY=your_remita_api_key
REMITA_MERCHANT_ID=your_remita_merchant_id
REMITA_SERVICE_TYPE_ID=your_remita_service_type_id
REMITA_SOURCE_ACCOUNT=your_remita_source_account
REMITA_CALLBACK_URL=https://yoursite.com/payment/callback

# Budpay
BUDPAY_SECRET_KEY=your_budpay_secret_key
BUDPAY_PUBLIC_KEY=your_budpay_public_key
BUDPAY_CALLBACK_URL=https://yoursite.com/payment/callback
BUDPAY_WEBHOOK_SECRET=your_budpay_webhook_secret

# Interswitch
INTERSWITCH_CLIENT_ID=your_interswitch_client_id
INTERSWITCH_CLIENT_SECRET=your_interswitch_client_secret
INTERSWITCH_MERCHANT_CODE=your_interswitch_merchant_code
INTERSWITCH_PAYABLE_CODE=your_interswitch_payable_code
INTERSWITCH_TERMINAL_ID=your_interswitch_terminal_id
INTERSWITCH_CALLBACK_URL=https://yoursite.com/payment/callback
INTERSWITCH_WEBHOOK_SECRET=your_interswitch_webhook_secret

# PayPal
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
PAYPAL_CURRENCY=USD
PAYPAL_BASE_URL=https://api.paypal.com
PAYPAL_CALLBACK_URL=https://yoursite.com/payment/callback
PAYPAL_CANCEL_URL=https://yoursite.com/payment/cancel

# M-Pesa (Safaricom)
MPESA_CONSUMER_KEY=your_mpesa_consumer_key
MPESA_CONSUMER_SECRET=your_mpesa_consumer_secret
MPESA_SHORTCODE=your_mpesa_shortcode
MPESA_PASSKEY=your_mpesa_passkey
MPESA_INITIATOR_NAME=your_mpesa_initiator
MPESA_SECURITY_CREDENTIAL=your_mpesa_security_credential
MPESA_CALLBACK_URL=https://yoursite.com/webhooks/payment-gateways/mpesa
MPESA_RESULT_URL=https://yoursite.com/webhooks/payment-gateways/mpesa
MPESA_TIMEOUT_URL=https://yoursite.com/webhooks/payment-gateways/mpesa

# MTN MoMo
MTNMOMO_COLLECTION_USER_ID=your_mtnmomo_collection_user_id
MTNMOMO_COLLECTION_API_KEY=your_mtnmomo_collection_api_key
MTNMOMO_COLLECTION_SUBSCRIPTION_KEY=your_mtnmomo_collection_subscription_key
MTNMOMO_DISBURSEMENT_USER_ID=your_mtnmomo_disbursement_user_id
MTNMOMO_DISBURSEMENT_API_KEY=your_mtnmomo_disbursement_api_key
MTNMOMO_DISBURSEMENT_SUBSCRIPTION_KEY=your_mtnmomo_disbursement_subscription_key
MTNMOMO_ENVIRONMENT=sandbox
MTNMOMO_CURRENCY=EUR
MTNMOMO_CALLBACK_URL=https://yoursite.com/webhooks/payment-gateways/mtnmomo

# Razorpay
RAZORPAY_KEY_ID=rzp_live_xxxxx
RAZORPAY_KEY_SECRET=your_razorpay_key_secret
RAZORPAY_ACCOUNT_NUMBER=your_razorpay_x_account_number
RAZORPAY_CURRENCY=INR
RAZORPAY_CALLBACK_URL=https://yoursite.com/payment/callback
RAZORPAY_WEBHOOK_SECRET=your_razorpay_webhook_secret

# Paddle
PADDLE_API_KEY=your_paddle_api_key
PADDLE_CLIENT_TOKEN=your_paddle_client_token
PADDLE_WEBHOOK_SECRET=your_paddle_webhook_secret
PADDLE_CURRENCY=USD
PADDLE_BASE_URL=https://api.paddle.com
PADDLE_CALLBACK_URL=https://yoursite.com/payment/callback

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

> Available on: **Paystack**, **Flutterwave**, **Seerbit**, **Monnify**, **Squad**, **Budpay**

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
https://yoursite.com/webhooks/payment-gateways/monnify
https://yoursite.com/webhooks/payment-gateways/squad
https://yoursite.com/webhooks/payment-gateways/remita
https://yoursite.com/webhooks/payment-gateways/budpay
https://yoursite.com/webhooks/payment-gateways/interswitch
https://yoursite.com/webhooks/payment-gateways/paypal
https://yoursite.com/webhooks/payment-gateways/mpesa
https://yoursite.com/webhooks/payment-gateways/mtnmomo
https://yoursite.com/webhooks/payment-gateways/razorpay
https://yoursite.com/webhooks/payment-gateways/paddle
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

> **Note:** Monnify implements `SubscriptionDriverInterface` but all methods throw `SubscriptionException`. Always wrap in try/catch or check before calling.

---

## Gateway-Specific Notes

### Monnify

Monnify's primary use case is **virtual accounts** (reserved bank accounts). The package supports:

- Reserved account creation (`createVirtualAccount`) via POST `/api/v2/bank-transfer/reserved-accounts`
- Single and bulk disbursements
- Authentication is automatic — the driver fetches and caches a Bearer token via Basic Auth

```php
$va = Payment::driver('monnify')->createVirtualAccount([
    'email'             => 'customer@example.com',
    'name'              => 'Jane Doe',
    'bvn'               => '12345678901',
    'currency_code'     => 'NGN',
    'contract_code'     => config('payment-gateways.gateways.monnify.contract_code'),
    'reference'         => 'VA_' . uniqid(),
    'split_percentages' => [],
]);
```

### Squad (GTCo)

```php
Payment::driver('squad')->initializePayment([
    'email'     => 'customer@example.com',
    'amount'    => 5000.00,
    'currency'  => 'NGN',
    'reference' => 'TXN_' . uniqid(),
]);

// Virtual account
Payment::driver('squad')->createVirtualAccount([
    'customer_identifier' => 'customer_001',
    'email'               => 'customer@example.com',
    'name'                => 'Jane Doe',
]);
```

### Remita

Remita's payment flow uses a **Remita Retrieval Reference (RRR)**. `initializePayment()` returns an RRR that you use to construct the checkout URL. Pass the RRR as `$reference` to `verifyPayment()`.

```php
$result = Payment::driver('remita')->initializePayment([
    'email'       => 'customer@example.com',
    'amount'      => 5000.00,
    'description' => 'Invoice #1001',
    'reference'   => 'ORDER_1001',
]);

// Redirect to checkout: $result['data']['authorization_url']
// After callback, verify with RRR:
Payment::driver('remita')->verifyPayment($result['data']['rrr']);
```

### Budpay

Budpay uses a dual-header auth: Bearer token + `Encryption` header (HMAC-SHA512). This is handled internally; no extra configuration required.

```php
Payment::driver('budpay')->initializePayment([
    'email'     => 'customer@example.com',
    'amount'    => 5000.00,
    'reference' => 'BDP_' . uniqid(),
    'currency'  => 'NGN',
]);
```

### Interswitch (Webpay)

Interswitch uses **OAuth2 client_credentials** — the token is fetched and cached automatically. Amount is stored in kobo internally. Currency uses ISO 4217 numeric codes (e.g. `566` for NGN).

```php
Payment::driver('interswitch')->initializePayment([
    'email'     => 'customer@example.com',
    'amount'    => 5000.00,
    'reference' => 'ISW_' . uniqid(),
    'currency'  => 'NGN',
]);
```

### PayPal

PayPal uses **OAuth2 client_credentials** — the access token is fetched and cached automatically. Amounts are in major currency units (USD, EUR, etc.) as decimal strings. The driver covers payments, billing subscriptions, and Payouts (bulk disbursements).

```php
$order = Payment::driver('paypal')->initializePayment([
    'amount'   => 50.00,
    'currency' => 'USD',
    'email'    => 'customer@example.com',
]);
// Redirect to $order['links'][n]['href'] where rel == 'approve'
// After approval, capture using the order ID:
Payment::driver('paypal')->verifyPayment($order['id']);
```

PayPal webhooks use RSA-SHA256 certificate-based signing (not HMAC). The handler verifies that all required PayPal signature headers are present as a guard.

### M-Pesa (Safaricom Kenya)

M-Pesa uses **STK Push** — a prompt is sent to the customer's phone. Payment is **asynchronous**; the result is delivered to your `MPESA_CALLBACK_URL`.

```php
$response = Payment::driver('mpesa')->initializePayment([
    'phone'  => '254712345678',   // international format, no +
    'amount' => 500,              // KES whole number
]);
$checkoutRequestId = $response['data']['checkout_request_id'];

// Poll or wait for callback, then verify:
Payment::driver('mpesa')->verifyPayment($checkoutRequestId);
```

M-Pesa does not send HMAC signatures — secure your callback URLs via HTTPS.

### MTN MoMo

MTN MoMo uses two separate products (Collections and Disbursements), each with their own API User + Key + Subscription Key. Payments are also **asynchronous** — a push is sent to the customer's wallet, and the result is delivered to your `MTNMOMO_CALLBACK_URL`.

```php
$response = Payment::driver('mtnmomo')->initializePayment([
    'phone'    => '256771234567',   // MSISDN, no +
    'amount'   => 1000,
    'currency' => 'UGX',
]);
$referenceId = $response['data']['reference'];

// Poll for status:
Payment::driver('mtnmomo')->verifyPayment($referenceId);

// Disbursement:
Payment::driver('mtnmomo')->transfer([
    'phone'    => '256771234567',
    'amount'   => 1000,
    'currency' => 'UGX',
    'narration' => 'Salary payout',
]);
```

### Razorpay

Razorpay uses **Basic Auth** (key_id:key_secret) on every request. Amounts are in paise (1 INR = 100 paise), handled internally by `convertAmount()`. The driver supports payments, subscriptions, payouts (Razorpay X account required), and payment links.

```php
$order = Payment::driver('razorpay')->initializePayment([
    'email'    => 'customer@example.com',
    'amount'   => 499.00,
    'currency' => 'INR',
    'name'     => 'Jane Doe',
    'phone'    => '+919876543210',
]);
// Pass $order['id'], $order['data']['key_id'], etc. to Razorpay checkout.js
```

### Paddle

Paddle is a **Merchant of Record** for SaaS / digital goods. The driver targets the Paddle Billing API. Plans map to Prices, payments to Transactions, and payment links to Paddle's native Payment Links.

```php
$transaction = Payment::driver('paddle')->initializePayment([
    'amount'      => 29.99,
    'currency'    => 'USD',
    'description' => 'Pro plan',
    'email'       => 'customer@example.com',
]);
// Redirect to $transaction['data']['authorization_url']
```

Paddle webhooks use HMAC-SHA256 with the `Paddle-Signature: ts=...;h1=...` format, verified automatically.

---

## Testing

Run the package's own test suite:

```bash
composer test          # all tests
composer test:unit     # unit tests only
composer test:feature  # feature tests only
```

The test suite covers:

| Test file                                   | What it tests                                            |
| ------------------------------------------- | -------------------------------------------------------- |
| `tests/Unit/PaymentManagerTest.php`         | driver resolution, interface detection, alias binding    |
| `tests/Unit/WebhookManagerTest.php`         | handler resolution, unknown gateway exception            |
| `tests/Unit/Drivers/PaystackDriverTest.php` | HTTP mock — initialize, verify, refund, error handling   |
| `tests/Unit/Drivers/RazorpayDriverTest.php` | HTTP mock — order creation, verify, refund, errors       |
| `tests/Feature/WebhookHandlingTest.php`     | full HTTP webhook flow — signature check, event dispatch |

In your own application, you can mock the `Payment` facade or bind a fake driver:

```php
// In your test
Payment::shouldReceive('driver->initializePayment')
    ->once()
    ->andReturn(['status' => true, 'data' => ['authorization_url' => 'https://...']]);
```

## Database Recording

When `PAYMENT_RECORDING_ENABLED=true`, use `PaymentRecorder` to persist activity:

```php
use NexusPay\PaymentMadeEasy\Services\PaymentRecorder;

$recorder = app(PaymentRecorder::class);

// After initializing a payment
$transaction = $recorder->recordTransaction('paystack', $requestData, $gatewayResponse);

// After verifying / receiving a webhook
$recorder->updateTransactionStatus('ORDER_001', 'successful', 'PS_GATEWAY_REF');

// Record a transfer
$transfer = $recorder->recordTransfer('paystack', $transferData, $transferResponse);

// Log a webhook event for auditing
$recorder->logWebhookEvent('paystack', $webhookEvent);
```

### Available Models

| Model                 | Table                   | Purpose                             |
| --------------------- | ----------------------- | ----------------------------------- |
| `PaymentTransaction`  | `payment_transactions`  | One-time payment records            |
| `PaymentSubscription` | `payment_subscriptions` | Subscription / billing records      |
| `PaymentTransfer`     | `payment_transfers`     | Disbursement / transfer records     |
| `PaymentWebhookLog`   | `payment_webhook_logs`  | Audit log for all incoming webhooks |

All models support polymorphic `payable` / `subscriber` / `initiator` relationships so you can link them to your own models (e.g. `User`, `Order`).

```php
// Attach a transaction to a user
$transaction->payable()->associate($user)->save();

// Scope helpers
PaymentTransaction::successful()->gateway('paystack')->get();
PaymentSubscription::active()->forEmail('user@example.com')->get();
PaymentTransfer::successful()->gateway('mtnmomo')->get();
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email babusunnah@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
